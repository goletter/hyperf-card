<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Slash\Adapter;

use Goletter\Card\Platforms\Slash\Client;
use Goletter\Card\Platforms\Slash\Exceptions\SlashApiException;
use Goletter\Card\Support\Result;

/**
 * Unified Result adapter: maps Slash Card API into unified Result envelopes where applicable.
 */
class CardAdapter
{
    /**
     * @var Client
     */
    protected Client $client;

    /** @var array<string, string> */
    protected array $cardStatus = [
        'active' => 'normal',
        'inactive' => 'frozen',
        'closed' => 'cancelled',
        'paused' => 'frozen',
    ];

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * List cards
     * @param array $params Query parameters
     *   - cursor: string - Cursor for pagination (from metadata.nextCursor in previous response)
     *   Note: Slash API only supports cursor-based pagination, limit parameter is not supported
     * @return array Response includes metadata.nextCursor for next page
     * @throws SlashApiException
     */
    public function cardList(array $params = []): array
    {
        // Remove limit if present, as Slash API doesn't support it
        unset($params['limit']);
        unset($params['offset']);
        $param = [
            'sort'=>'createdAt',
            'sortDirection'=>'DESC',
            'filter:status'=>'active',
            // 'filter:cardGroupId'=>'card_group_1vds6ihnqu6qy',
            'cursor'=>$params['cursor'] ?? '',
        ];
        $result = $this->client->get('/card', $param);
        if(isset($result['items'])){
            $metadata = $result['metadata'] ?? [];
            $data = [
                'data' => $result['items'],
                'pageSize' => 100,
                'total' => $metadata['count']??0,
                'cursor'=> $metadata['nextCursor']??'',
            ];
            return Result::success($data);
        }else{
            return Result::error((string) ($result['msg'] ?? 'error'));
        }
    }

    /**
     * List cards with pagination helper (cursor-based).
     *
     * @return array Result envelope; data.cursor is nextCursor when more pages exist
     * @throws SlashApiException
     */
    public function listCardsPaginated(?string $cursor = null): array
    {
        return $this->cardList(['cursor' => $cursor ?? '']);
    }

    /**
     * Get card details (vault origin, include_pan / include_cvv).
     *
     * With X-RSA-Public-Key set on the client, Slash returns PAN/CVV as RSA-OAEP base64 ciphertext; otherwise plaintext.
     * If {@see Client::getPrivateKeyPem()} is set, ciphertext is decrypted before mapping to cardNo/cvv.
     *
     * @param array $params must include card_id
     * @return array
     * @throws SlashApiException
     */
    public function cardInfo(array $params = []): array
    {
        $param = [
            'include_pan' => 'true',
            'include_cvv' => 'true',
        ];
        $vault = $this->client->vaultBaseUriForCardSecrets();
        $result = $this->client->get("/card/{$params['card_id']}", $param, true, $vault);

        if(isset($result['id'])){
            $cardGetLimits = $this->cardGetLimits(['card_id'=>$params['card_id']]);

            list($maxOnPercent, $totalTransactionLimit, $availableTransactionLimit,$transactionLimitType) = [0,0,0,'unlimited'];
            $limits = $result['spendingConstraint']??[];
            if(!empty($limits)) {
                $maxOnPercent = bcdiv((string)($limits['spendingRule']['transactionSizeLimit']['maximum']['amountCents']??0) ,"100",2);
                $totalTransactionLimit = bcdiv((string)($limits['spendingRule']['utilizationLimit']['limitAmount']['amountCents']??0),"100",2);
                $availableTransactionLimit = $cardGetLimits['data']['availableBalance']??0;
                $transactionLimitType = 'limited';
            }

            $pan = (string) ($result['pan'] ?? '');
            $cvv = (string) ($result['cvv'] ?? '');
            if ($this->client->hasRsaPublicKey()) {
                $pem = $this->client->getPrivateKeyPem();
                if ($pem !== '') {
                    $pan = $this->decryptSlashOaepField($pan, $pem);
                    $cvv = $this->decryptSlashOaepField($cvv, $pem);
                }
            }
            
            $data = [              
                'cardId'=>$result['id'],
                'cardNo'=>$pan,
                'cardCurrency'=>'USD',
                'cardScheme'=>'VISA',
                'cardStatus'=>$this->cardStatus[$result['status']],
                'cardType'=>'share',
                'createdAt'=>date('Y-m-d H:i:s',strtotime($result['createdAt'])),
                'expirationDate'=>$result['expiryMonth'].'/'.$result['expiryYear'],
                'cvv'=>$cvv,
                'maxOnPercent'=>$maxOnPercent,
                'nickname' => $result['name'],
                'totalTransactionLimit'=>$totalTransactionLimit,
                'transactionLimitType'=>$transactionLimitType,
                'availableTransactionLimit'=>$availableTransactionLimit,
            ];
             return Result::success($data);
        }else{
            return Result::error((string) ($result['msg'] ?? 'error'));
        }
    }

    /**
     * Summary of cardGetLimits
     * @param mixed $params
     * @return array
     */
    public function cardGetLimits(array $params): array
    {
        $param = [];

        $result = $this->client->get("/card/{$params['card_id']}/utilization", $param);

        if(isset($result['spend'])){
            $spend = (string)($result['spend']['amountCents']??'0.00');
            if($spend > 0) $spend = bcdiv($spend,"100",2);

            $availableBalance = (string)($result['availableBalance']['amountCents']??'0.00');
            if($availableBalance) $availableBalance = bcdiv($availableBalance,"100",2);

            $result = [
                'spend'=>$spend,
                'availableBalance'=>$availableBalance
            ];
            return Result::success($result);
        }else{
            return Result::error((string) ($result['msg'] ?? 'error'));
        }
    }

    /**
     * Summary of transactionDetail
     * @param mixed $params
     * @return array
     */
    public function transactionDetail(array $params): array
    {

        $param = [
            'filter:cardId'=>$params['card_id'],
            'cursor'=>$params['cursor']??''
        ];

        if(!empty($params['from_created_at']) && !empty($params['to_created_at']))
        {
            $param['filter:from_date'] = $params['from_created_at'];
            $param['filter:to_date'] = $params['to_created_at'];
        }
        
        $result = $this->client->get("/transaction", $param);
        if(isset($result['items'])){
            return Result::success($result);
        }else{
            return Result::error((string) ($result['msg'] ?? 'error'));
        }
    }

    /**
     * Summary of transactionGetIdDetail
     * @param mixed $params
     * @return array{code: int, data: array, msg: string}
     */
    public function transactionGetIdDetail(array $params): array
    {
        $param = [];
        
        $result = $this->client->get("/transaction/".$params['entity_id'], $param);
        if(isset($result['id'])){
            return Result::success($result);
        }else{
            return Result::error((string) ($result['msg'] ?? 'error'));
        }
    }

    /**
     * Summary of getCardDetails
     * @param mixed $params
     * @return array{code: int, data: array, msg: string}
     */
    public function getCardDetails(array $params): array
    {
        $param = [];
        $result = $this->client->get("/card/".$params['entity_id'], $param);
        if(isset($result['id'])){
            return Result::success($result);
        }else{
            return Result::error((string) ($result['msg'] ?? 'error'));
        }
    }

    /**
     * Create virtual card
     * @param array $data Card creation data (card_type, name, account_id, etc.)
     * @return array
     * @throws SlashApiException
     */
    public function createCard(array $data): array
    {
        return $this->client->post('/card', $data);
    }

    /**
     * @param array<string, mixed> $params Query filters, e.g. search:merchant_name
     */
    public function cardMerchant(array $params = []): array
    {
        return $this->client->get('/merchant', $params);
    }

    public function cardGroup() :array
    {
        return $this->client->get('/card-group');
    }

    public function cardProduct(): array
    {
        return $this->client->get('/card-product');
    }
    

    /**
     * Create physical card
     * @param array $data Card creation data
     * @return array
     * @throws SlashApiException
     */
    public function createPhysicalCard(array $data): array
    {
        return $this->client->post('/card', array_merge($data, ['card_type' => 'physical']));
    }

    /**
     * Update card
     * @param string $cardId
     * @param array $data Card update data
     * @return array
     * @throws SlashApiException
     */
    public function updateCard(array $params): array
    {
        $param = [];
        if(empty($params['transaction_limit'])) {
            return Result::success();
        }
        if(!empty($params['nickname'])) $param['name'] = $params['nickname'];
        if(!empty($params['max_on_percent'])) $param['spendingConstraint']['spendingRule']['transactionSizeLimit'] = [
            'minimum'=>['amountCents'=>0],
            'maximum'=>['amountCents'=>$params['max_on_percent'] * 100]
        ]; // 单笔限额
        // $param['spendingConstraint']['spendingConstraint']['utilizationLimit']['limitAmount'] = ['amountCents'=>$params['transaction_limit'] * 100];  // 设置默认限额
        $cents = (int) bcmul((string)$params['transaction_limit'], '100', 0);
        $param['spendingConstraint']['spendingRule']['utilizationLimit']['limitAmount'] = ['amountCents'=>$cents];
        $param['spendingConstraint']['spendingRule']['utilizationLimit']['preset'] = 'collective';

        $merchants = $params['merchants']
            ?? $this->client->getConfig()->merchantAllowlist;
        if ($merchants !== []) {
            $param['spendingConstraint']['merchantRule'] = [
                'merchants' => array_values($merchants),
                'restriction' => 'allowlist',
            ];
        }

        $result = $this->client->patch("/card/{$params['card_id']}", $param);

        if(isset($result['id'])){
            return Result::success();
        }else{
            return Result::error((string) ($result['msg'] ?? 'error'));
        }
    }

    /**
     * Partially update card
     * @param string $cardId
     * @param array $data Card update data
     * @return array
     * @throws SlashApiException
     */
    public function patchCard(string $cardId, array $data): array
    {
        return $this->client->patch("/card/{$cardId}", $data);
    }

    /**
     * Freeze card
     * @param string $cardId
     * @param array $data Freeze reason and other data
     * @return array
     * @throws SlashApiException
     */
    public function cardFreeze(array $params = []): array
    {
        $param = ['status'=>'paused'];
        $result = $this->client->patch("/card/{$params['card_id']}", $param);
        if(isset($result['status'])){
            return Result::success(['cardStatus'=>$this->cardStatus[$result['status']??'NO']]);
        }else{
            return Result::error((string) ($result['msg'] ?? 'error'));
        }
    }

    /**
     * Unfreeze card
     * @param string $cardId
     * @param array $data Unfreeze reason and other data
     * @return array
     * @throws SlashApiException
     */
    public function cardUnfreeze(array $params = []): array
    {
        $param = ['status'=>'active'];
        $result = $this->client->patch("/card/{$params['card_id']}", $param);
        if(isset($result['status'])){
            return Result::success(['cardStatus'=>$this->cardStatus[$result['status']??'NO']]);
        }else{
            return Result::error((string) ($result['msg'] ?? 'error'));
        }
    }

    /**
     * cancel card
     * @param array $params
     * @return array
     */
    public function cardCancel(array $params = []): array
    {
        $param = ['status'=>'closed'];
        $result = $this->client->patch("/card/{$params['card_id']}", $param);
        if(isset($result['status'])){
            return Result::success(['cardStatus'=>$this->cardStatus[$result['status']??'NO']]);
        }else{
            return Result::error((string) ($result['msg'] ?? 'error'));
        }
    }

    /**
     * Cancel card
     * @param string $cardId
     * @param array $data Cancel reason and other data
     * @return array
     * @throws SlashApiException
     */
    public function cancelCard(string $cardId, array $data = []): array
    {
        return $this->client->post("/card/{$cardId}/cancel", $data);
    }

    /**
     * Delete card
     * @param string $cardId
     * @return array
     * @throws SlashApiException
     */
    public function deleteCard(string $cardId): array
    {
        return $this->client->delete("/card/{$cardId}");
    }

    /**
     * Set spending constraints
     * @param string $cardId
     * @param array $constraints Spending constraints data
     * @return array
     * @throws SlashApiException
     */
    public function setSpendingConstraints(string $cardId, array $constraints): array
    {
        return $this->client->put("/card/{$cardId}/spending-constraints", $constraints);
    }

    /**
     * Get spending constraints
     * @param string $cardId
     * @return array
     * @throws SlashApiException
     */
    public function getSpendingConstraints(string $cardId): array
    {
        return $this->client->get("/card/{$cardId}/spending-constraints");
    }

    private function decryptSlashOaepField(string $value, string $privateKeyPem): string
    {
        if ($value === '') {
            return $value;
        }
        $raw = base64_decode($value, true);
        if ($raw === false) {
            return $value;
        }
        $plain = '';
        if (@openssl_private_decrypt($raw, $plain, $privateKeyPem, OPENSSL_PKCS1_OAEP_PADDING)) {
            return $plain;
        }

        return $value;
    }

    /**
     * Summary of getAccount
     * @param array $params
     * @return array
     */
    public function getAccount(array $params = []): array
    {
        return $this->client->get("/account");
    }

    /**
     * Summary of getAccountAmount
     * @param array $params
     * @return array
     */
    public function getAccountAmount(array $params = []): array
    {
        $accountId = $params['account_id'];
        return $this->client->get("/account/{$accountId}/balance");
    }


    public function getVirtualAccount(array $params = []): array
    {
        $virtualAccountId = $params['account_id'];
        return $this->client->get("/virtual-account/{$virtualAccountId}");
    }
}
