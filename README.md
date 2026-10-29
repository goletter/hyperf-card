# Goletter Card SDK

多平台卡片 API SDK，支持 Airwallex、Slash、Lampay、Photonpay、Wasabi、Youcardx。

## 安装

```bash
composer require goletter/hyperf-card
```

## 依赖要求

- PHP >= 8.1
- ext-openssl / ext-json
- GuzzleHttp >= 7.0

## 架构说明

本包分两层（请按场景选用）：

| 层级 | 入口 | 命名空间 | 行为 |
|------|------|----------|------|
| **SDK 层** | `$bundle->card()` / `api()` | `Platforms\{Vendor}\Api\CardApi` | 官方路径 + 原始 JSON；失败抛 `*ApiException` |
| **适配层** | `$bundle->adapter()` | `Platforms\{Vendor}\Adapter\CardAdapter` | `{code,msg,data}` + 字段/状态映射（Lampay、Youcardx 无适配层，`adapter()` 为 `null`） |

推荐：`CardFactory` → `CardPlatformBundle` → 按需 `card()` 或 `adapter()`。

## 工厂模式（推荐，以 Slash 为例）

统一入口是 `CardFactory`，返回 `CardPlatformBundle`：

```text
CardFactory::slash($config)
        │
        ▼
 CardPlatformBundle
  ├─ name         = 'slash'
  ├─ platform     = Slash 实例
  ├─ client       = Slash\Client
  ├─ card()/api() → Slash\Api\CardApi          （薄 SDK）
  └─ adapter()    → Slash\Adapter\CardAdapter  （Result 适配层）
```

### 1. 静态创建（任意 PHP 项目）

三种写法等价，任选其一：

```php
use Goletter\Card\CardFactory;
use Goletter\Card\Platforms\Slash\Exceptions\SlashApiException;

$config = [
    'base_url' => 'https://api.slash.com',   // 也可用 baseUrl
    'api_key'  => 'your-api-key',            // 也可用 apiKey
    'timeout'  => 60,
    // 可选：敏感信息解密
    // 'rsa_public_key' => '...',
    // 'private_key' => file_get_contents('/secure/slash-private.pem'),
    // 'vault_base_url' => 'https://vault.slash.com',
    // 可选：更新限额时的商户白名单
    // 'merchant_allowlist' => ['merchant_v2_xxx'],
];

// ① 命名方法（IDE 提示最好）
$bundle = CardFactory::slash($config);

// ② 字符串平台名
$bundle = CardFactory::make('slash', $config);

// ③ 数字别名（与 publish/card.php 中 platforms 映射一致，默认 1 = slash）
$bundle = CardFactory::make(1, $config);
```

### 2. 通过 Bundle 调用 Slash

#### 适配层（业务统一字段，推荐现有业务继续用）

```php
$adapter = $bundle->adapter(); // Slash\Adapter\CardAdapter

try {
    // 返回 {code, msg, data}
    $page = $adapter->cardList([
        'cursor' => '',   // 首页传空；下一页用上一页 data.cursor
    ]);

    if ($page['code'] !== 1) {
        throw new \RuntimeException($page['msg'] ?? 'list failed');
    }

    foreach ($page['data']['data'] ?? [] as $card) {
        // ...
    }

    $info = $adapter->cardInfo(['card_id' => 'card_xxx']);
    $adapter->cardFreeze(['card_id' => 'card_xxx']);
    $adapter->cardUnfreeze(['card_id' => 'card_xxx']);
    $adapter->cardCancel(['card_id' => 'card_xxx']);
} catch (SlashApiException $e) {
    echo $e->getMessage();
    print_r($e->getResponse());
}
```

分页拉取全部卡片：

```php
$adapter = $bundle->adapter();
$allCards = [];
$cursor = '';

do {
    $page = $adapter->listCardsPaginated($cursor === '' ? null : $cursor);
    if ($page['code'] !== 1) {
        break;
    }
    $allCards = array_merge($allCards, $page['data']['data'] ?? []);
    $cursor = (string) ($page['data']['cursor'] ?? '');
} while ($cursor !== '');
```

#### SDK 层（官方原始响应）

```php
$api = $bundle->card(); // 等同 $bundle->api()

// 原始 Slash JSON；失败直接抛 SlashApiException
$raw = $api->listCards(['cursor' => '', 'sort' => 'createdAt', 'sortDirection' => 'DESC']);
$detail = $api->getCard('card_xxx', ['include_pan' => 'true', 'include_cvv' => 'true']);
$api->patchCard('card_xxx', ['status' => 'paused']);
```

### 3. Hyperf 中使用（配置 + DI）

发布配置：

```bash
php bin/hyperf.php vendor:publish goletter/hyperf-card
```

编辑 `config/autoload/card.php` 中的 `slash` 段（可用环境变量）：

```php
'slash' => [
    'base_url' => env('SLASH_BASE_URL', 'https://api.slash.com'),
    'api_key' => env('SLASH_API_KEY', ''),
    'timeout' => 60,
    // 'rsa_public_key' => env('SLASH_RSA_PUBLIC_KEY', ''),
    // 'private_key' => env('SLASH_PRIVATE_KEY', ''),
    // 'vault_base_url' => env('SLASH_VAULT_URL', ''),
    // 'merchant_allowlist' => [],
],
```

注入工厂后，**配置文件会自动合并**，调用时只需传覆盖项：

```php
use Goletter\Card\CardFactory;
use Hyperf\Di\Annotation\Inject;

class CardService
{
    #[Inject]
    protected CardFactory $cardFactory;

    public function listSlashCards(?string $cursor = null): array
    {
        // 读取 config/autoload/card.php 的 slash 配置
        $bundle = $this->cardFactory->create('slash');

        // 临时覆盖（例如切换账号 api_key）
        // $bundle = $this->cardFactory->create('slash', ['api_key' => $otherKey]);

        // 业务统一字段 → adapter()；官方原始 JSON → card()/api()
        return $bundle->adapter()->cardList([
            'cursor' => $cursor ?? '',
        ]);
    }
}
```

未注入时也可手动把配置灌进共享工厂：

```php
$factory = new CardFactory(require BASE_PATH . '/config/autoload/card.php');
CardFactory::setShared($factory);

$bundle = CardFactory::slash(); // 使用配置文件中的 slash 段
```

### 4. 其它平台（同一套工厂）

| 写法 | 平台 |
|------|------|
| `CardFactory::airwallex($config)` / `make(0, …)` | Airwallex |
| `CardFactory::slash($config)` / `make(1, …)` | Slash |
| `CardFactory::lampay($config)` / `make(2, …)` | Lampay |
| `CardFactory::photonpay($config)` / `make(3, …)` | Photonpay |
| `CardFactory::wasabi($config)` / `make(4, …)` | Wasabi |
| `CardFactory::youcardx($config)` / `make(5, …)` | Youcardx |

Airwallex Token 持久化示例：

```php
use Goletter\Card\Contract\TokenPersisterInterface;

$bundle = CardFactory::airwallex([
    'api_key' => '...',
    'client_id' => '...',
    'card_accounts_id' => 1,
    'token_persister' => $container->get(TokenPersisterInterface::class),
    // 或：'token_persister' => fn ($accountId, array $token) => { ... },
]);
```

## Airwallex API

### 配置

```php
use Goletter\Card\Platforms\Airwallex\Airwallex;

$airwallex = new Airwallex([
    'baseUrl' => 'https://api-demo.airwallex.com',  // API 基础地址
    'apiKey' => 'your-api-key',                      // API Key
    'clientId' => 'your-client-id',                  // Client ID
    'timeout' => 60,                                 // 请求超时时间（秒）
    'requestSource' => 4,                            // 请求来源标识
]);
```

### 配置参数说明

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| baseUrl | string | 否 | `https://api-demo.airwallex.com` | API 基础地址 |
| apiKey | string | 是 | - | API Key |
| clientId | string | 是 | - | Client ID |
| timeout | int | 否 | 60 | 请求超时时间（秒） |
| log_request | bool | 否 | false | 是否记录每次请求耗时（见「调用日志 / 耗时」） |
| requestSource | int | 否 | 4 | 请求来源标识 |

### 认证 API

#### 登录获取 Token

```php
// 自动获取并管理 token
$result = $airwallex->authentication()->login();

// 返回结果包含 token
// $result['token']
```

**注意：** SDK 会自动管理 token 的获取和刷新，无需手动处理。当 token 过期时，会自动重新获取。

### 卡片 API

#### 获取卡片列表

```php
$cards = $airwallex->card()->listCards([
    'page_num' => 0,      // 页码，从 0 开始
    'page_size' => 10,    // 每页数量
]);

// 返回卡片列表数据
```

#### 获取卡片详情

```php
$cardId = 'd467705b-6ef1-42b0-8b67-aa74caaa6b06';
$cardDetails = $airwallex->card()->getCardDetails($cardId);
```

#### 获取卡片限额

```php
$cardId = 'd467705b-6ef1-42b0-8b67-aa74caaa6b06';
$limits = $airwallex->card()->getCardLimits($cardId);
```

#### 创建卡片

```php
$newCard = $airwallex->card()->createCard([
    // 卡片创建所需的数据
    // 具体字段请参考 Airwallex API 文档
]);
```

#### 更新卡片

```php
$cardId = 'd467705b-6ef1-42b0-8b67-aa74caaa6b06';
$updatedCard = $airwallex->card()->updateCard($cardId, [
    // 需要更新的字段
]);
```

#### 冻结卡片

```php
$cardId = 'd467705b-6ef1-42b0-8b67-aa74caaa6b06';
$result = $airwallex->card()->freezeCard($cardId, [
    // 可选：冻结原因等数据
]);
```

#### 解冻卡片

```php
$cardId = 'd467705b-6ef1-42b0-8b67-aa74caaa6b06';
$result = $airwallex->card()->unfreezeCard($cardId, [
    // 可选：解冻原因等数据
]);
```

#### 取消卡片

```php
$cardId = 'd467705b-6ef1-42b0-8b67-aa74caaa6b06';
$result = $airwallex->card()->cancelCard($cardId, [
    // 可选：取消原因等数据
]);
```

### 手动设置 Token

如果已有有效的 token，可以手动设置，避免重复登录：

```php
$airwallex->setAccessToken('your-token', $expiresAt); // $expiresAt 为可选的过期时间戳
```

### 异常处理

SDK 提供了两种异常类型：

1. **AirwallexApiException** - 通用 API 异常
2. **AirwallexTokenExpiredException** - Token 过期异常（继承自 AirwallexApiException）

```php
use Goletter\Card\Platforms\Airwallex\Exceptions\AirwallexApiException;
use Goletter\Card\Platforms\Airwallex\Exceptions\AirwallexTokenExpiredException;

try {
    $cards = $airwallex->card()->listCards();
} catch (AirwallexTokenExpiredException $e) {
    // Token 过期，SDK 会自动重新获取，但也可以手动处理
    echo "Token expired: " . $e->getMessage();
} catch (AirwallexApiException $e) {
    // 其他 API 错误
    echo "API Error: " . $e->getMessage();
    $response = $e->getResponse(); // 获取错误响应数据
}
```

### 完整使用示例

```php
<?php

use Goletter\Card\Platforms\Airwallex\Airwallex;
use Goletter\Card\Platforms\Airwallex\Exceptions\AirwallexApiException;

// 初始化
$airwallex = new Airwallex([
    'baseUrl' => 'https://api-demo.airwallex.com',
    'apiKey' => 'your-api-key',
    'clientId' => 'your-client-id',
]);

try {
    // 登录（可选，SDK 会在需要时自动登录）
    $airwallex->authentication()->login();
    
    // 获取卡片列表
    $cards = $airwallex->card()->listCards([
        'page_num' => 0,
        'page_size' => 10,
    ]);
    
    // 获取第一张卡片的详情
    if (!empty($cards['items'])) {
        $firstCardId = $cards['items'][0]['id'];
        $cardDetails = $airwallex->card()->getCardDetails($firstCardId);
        $cardLimits = $airwallex->card()->getCardLimits($firstCardId);
    }
    
} catch (AirwallexApiException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    print_r($e->getResponse());
}
```

## Slash API

> 推荐使用 [工厂模式](#工厂模式推荐以-slash-为例) 创建：`CardFactory::slash($config)`。

### 配置

```php
use Goletter\Card\CardFactory;

// 推荐
$bundle = CardFactory::slash([
    'base_url' => 'https://api.slash.com',
    'api_key' => 'your-api-key',
    'timeout' => 60,
]);

// 也可直接 new（不经过工厂）
use Goletter\Card\Platforms\Slash\Slash;
$slash = new Slash([
    'base_url' => 'https://api.slash.com',
    'api_key' => 'your-api-key',
    'timeout' => 60,
]);
```

### 配置参数说明

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| base_url / baseUrl | string | 否 | `https://api.slash.com` | API 基础地址 |
| api_key / apiKey | string | 是 | - | API Key（`X-API-Key`） |
| timeout | int | 否 | 60 | 请求超时（秒） |
| log_request | bool | 否 | false | 是否记录每次请求耗时（见「调用日志 / 耗时」） |
| rsa_public_key | string | 否 | - | 请求敏感字段时的 RSA 公钥 |
| private_key | string | 否 | - | 解密 PAN/CVV 的私钥 PEM |
| vault_base_url | string | 否 | 自动推导 | 敏感读卡 vault 地址 |
| merchant_allowlist | array | 否 | `[]` | `updateCard` 商户白名单 |

### 卡片 API

以下示例基于工厂适配层：`$adapter = CardFactory::slash($config)->adapter();`

#### 获取卡片列表

Slash **仅支持 cursor 分页**（不支持 `limit` / `offset`）。适配层返回 `Result` 信封：

```php
$adapter = CardFactory::slash($config)->adapter();

$result = $adapter->cardList(['cursor' => '']);
// $result['code'] === 1
// $result['data']['data'] / $result['data']['cursor']

$allCards = [];
$cursor = '';
do {
    $result = $adapter->listCardsPaginated($cursor === '' ? null : $cursor);
    $allCards = array_merge($allCards, $result['data']['data'] ?? []);
    $cursor = (string) ($result['data']['cursor'] ?? '');
} while ($cursor !== '');
```

#### 获取卡片详情

```php
$detail = $adapter->cardInfo(['card_id' => 'card-id']);
// $detail['data']['cardNo'] / cvv / cardStatus ...
```

#### SDK 层（原始 Slash API）

```php
$api = CardFactory::slash($config)->card();
$raw = $api->listCards(['cursor' => '']);
$card = $api->getCard('card-id', ['include_pan' => 'true', 'include_cvv' => 'true']);
```

#### 创建虚拟卡片

```php
$newCard = $slash->card()->createCard([
    'card_type' => 'virtual',
    'name' => 'Card Name',
    'account_id' => 'account-id',
    // 其他创建所需的数据
    // 具体字段请参考 Slash API 文档
]);
```

#### 创建实体卡片

```php
$newCard = $slash->card()->createPhysicalCard([
    'name' => 'Card Name',
    'account_id' => 'account-id',
    // 其他创建所需的数据
]);
```

#### 更新卡片

```php
$cardId = 'card-id';
$updatedCard = $slash->card()->updateCard($cardId, [
    // 需要更新的字段
]);
```

#### 部分更新卡片

```php
$cardId = 'card-id';
$updatedCard = $slash->card()->patchCard($cardId, [
    // 需要更新的字段（部分更新）
]);
```

#### 冻结卡片

```php
$cardId = 'card-id';
$result = $slash->card()->freezeCard($cardId, [
    // 可选：冻结原因等数据
]);
```

#### 解冻卡片

```php
$cardId = 'card-id';
$result = $slash->card()->unfreezeCard($cardId, [
    // 可选：解冻原因等数据
]);
```

#### 取消卡片

```php
$cardId = 'card-id';
$result = $slash->card()->cancelCard($cardId, [
    // 可选：取消原因等数据
]);
```

#### 删除卡片

```php
$cardId = 'card-id';
$result = $slash->card()->deleteCard($cardId);
```

#### 设置消费限制

```php
$cardId = 'card-id';
$result = $slash->card()->setSpendingConstraints($cardId, [
    // 消费限制规则
    // 例如：商户类别、交易金额等限制
]);
```

#### 获取消费限制

```php
$cardId = 'card-id';
$constraints = $slash->card()->getSpendingConstraints($cardId);
```

### 异常处理

SDK 提供了两种异常类型：

1. **SlashApiException** - 通用 API 异常
2. **SlashTokenExpiredException** - API Key 无效或过期异常（继承自 SlashApiException）

```php
use Goletter\Card\Platforms\Slash\Exceptions\SlashApiException;
use Goletter\Card\Platforms\Slash\Exceptions\SlashTokenExpiredException;

try {
    $cards = $slash->card()->listCards();
} catch (SlashTokenExpiredException $e) {
    // API Key 无效或过期
    echo "API Key invalid: " . $e->getMessage();
} catch (SlashApiException $e) {
    // 其他 API 错误
    echo "API Error: " . $e->getMessage();
    $response = $e->getResponse(); // 获取错误响应数据
}
```

### 完整使用示例

```php
<?php

use Goletter\Card\Platforms\Slash\Slash;
use Goletter\Card\Platforms\Slash\Exceptions\SlashApiException;

// 初始化
$slash = new Slash([
    'baseUrl' => 'https://api.slash.com',
    'apiKey' => 'your-api-key',
]);

try {
    // 方式一：循环获取所有卡片数据
    $allCards = [];
    $cursor = null;
    
    do {
        $response = $slash->card()->listCardsPaginated($cursor);
        
        // 合并当前页数据
        if (isset($response['data']) && is_array($response['data'])) {
            $allCards = array_merge($allCards, $response['data']);
        }
        
        // 获取下一页游标
        $cursor = $response['metadata']['nextCursor'] ?? null;
    } while ($cursor !== null);
    
    // 方式二：逐页处理（适合大数据量，避免内存占用过大）
    $cursor = null;
    do {
        $response = $slash->card()->listCardsPaginated($cursor);
        
        // 处理当前页的卡片
        foreach ($response['data'] ?? [] as $card) {
            // 处理每张卡片，例如保存到数据库
            // processCard($card);
        }
        
        // 获取下一页游标
        $cursor = $response['metadata']['nextCursor'] ?? null;
    } while ($cursor !== null);
    
    // 获取第一张卡片的详情
    if (!empty($cards['data'])) {
        $firstCardId = $cards['data'][0]['id'];
        $cardDetails = $slash->card()->getCard($firstCardId);
        $constraints = $slash->card()->getSpendingConstraints($firstCardId);
    }
    
    // 创建虚拟卡片
    $newCard = $slash->card()->createCard([
        'card_type' => 'virtual',
        'name' => 'My Virtual Card',
        'account_id' => 'account-id',
    ]);
    
} catch (SlashApiException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    print_r($e->getResponse());
}
```

## Lampay API

基于 [Lampay Consumer API](https://docs.lampay.app/)（Base URL: `https://api.lampay.app`）。
认证使用 **API Key + RSA-SHA256 请求签名 / 响应验签**，发卡等敏感接口使用 **RSA-OAEP-AES-256-GCM** 加密信封。

### 配置

```php
use Goletter\Card\Platforms\Lampay\Lampay;

$lampay = new Lampay([
    'base_url' => 'https://api.lampay.app',
    'api_key' => 'your-api-key',
    // 客户端鉴权私钥（签名）
    'private_key' => file_get_contents('/secure/client-auth-private.pem'),
    // 平台鉴权公钥（验签）
    'public_key' => file_get_contents('/secure/platform-auth-public.pem'),
    // 可选：加解密（发卡 / 敏感信息 / Webhook）
    'merchant_enc_private_key' => file_get_contents('/secure/client-enc-private.pem'),
    'platform_enc_public_key' => file_get_contents('/secure/platform-enc-public.pem'),
    'webhook_receiver_key_id' => 'receiver-api-key-biz-id',
    'provider_type' => '10010105',
    'api_version' => '1',
    'timeout' => 60,
]);
```

### 配置参数说明

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| base_url / baseUrl | string | 否 | `https://api.lampay.app` | API 基础地址 |
| api_key / apiKey | string | 是 | - | API Key 调用凭证 |
| private_key / merchantAuthPrivateKeyPem | string | 是 | - | 客户端鉴权私钥 PEM |
| public_key / platformAuthPublicKeyPem | string | 是 | - | 平台鉴权公钥 PEM（响应/Webhook 验签） |
| merchant_enc_private_key | string | 否 | - | 客户端加密私钥（解密敏感响应/Webhook） |
| platform_enc_public_key | string | 否 | - | 平台加密公钥（加密发卡请求） |
| webhook_receiver_key_id | string | 否 | - | Webhook 接收方 API Key 业务 ID |
| provider_type | string | 否 | `10010105` | 部分卡片路径中的提供商类型 |
| api_version | string | 否 | `1` | `X-API-VERSION` |
| timeout | int | 否 | 60 | 请求超时（秒） |

### 卡片 API

```php
// 产品组列表（首次只读联调）
$groups = $lampay->card()->listProductGroups(['page' => 0, 'size' => 20]);

// 卡片列表（分页，page 从 0 开始）
$cards = $lampay->card()->listCards(['page' => 0, 'size' => 20]);

// 卡片详情
$detail = $lampay->card()->getCard('VC_xxx');

// 敏感信息（需配置加密私钥；自动解密）
$reveal = $lampay->card()->revealSensitive('VC_xxx');
// $reveal['sensitive'] => pan / cvv / expiryMonth / expiryYear

// 发卡预检 + 加密发卡（需配置平台加密公钥）
$lampay->card()->createCardPrecheck($payload);
$result = $lampay->card()->createCard($payload, $idempotencyKey);

// 更新消费规则 / 别名
$lampay->card()->updateSpendingControls(['cardInfoBizId' => 'VC_xxx', 'spendingRule' => [...]]);
$lampay->card()->updateRemark('VC_xxx', 'Ops Card');

// 冻结 / 解冻 / 注销
$lampay->card()->freezeCard('VC_xxx');
$lampay->card()->unfreezeCard('VC_xxx');
$lampay->card()->cancelCard('VC_xxx');

// 交易列表
$txs = $lampay->card()->listTransactions(['cardInfoBizId' => 'VC_xxx', 'page' => 0, 'size' => 20]);
```

### Webhook

```php
$handled = $lampay->webhook()->handle(getallheaders(), file_get_contents('php://input'));
// $handled['event'] / delivery_biz_id / event_type
```

### 异常处理

```php
use Goletter\Card\Platforms\Lampay\Exceptions\LampayApiException;

try {
    $cards = $lampay->card()->listCards(['page' => 0, 'size' => 20]);
} catch (LampayApiException $e) {
    echo $e->getMessage();
    print_r($e->getResponse());
}
```
## Photonpay API

### 配置

```php
use Goletter\Card\Platforms\Photonpay\Photonpay;

$photonpay = new Photonpay([
    'base_url' => 'https://x-api.photonpay.com',
    'api_key' => 'your-api-key',
    'api_secret' => 'your-api-secret',
    'private_key' => 'your-rsa-private-key', // 请求签名
    'token' => 'optional-existing-token',
    'timeout' => 60,
]);
```

### 配置参数说明

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| base_url / baseUrl | string | 否 | `https://x-api.photonpay.com` | API 基础地址 |
| api_key / apiKey | string | 是 | - | API Key |
| api_secret / apiSecret | string | 是* | - | API Secret（无 token 时用于登录） |
| private_key | string | 否 | - | RSA 私钥（X-PD-SIGN） |
| token | string | 否 | - | 已有 access token |
| verify_ssl | bool | 否 | `true` | TLS 证书校验 |
| timeout | int | 否 | 60 | 请求超时时间（秒） |

### 认证 API

```php
$result = $photonpay->authentication()->login();
// $result['token'] / $result['access_token']
```

未传入 `token` 或临近过期时，SDK 会自动调用 `/api/v1/auth/token` 刷新。

### 卡片 API

```php
use Goletter\Card\CardFactory;

$bundle = CardFactory::photonpay($config);

// 适配层 → {code, msg, data}
$adapter = $bundle->adapter();
$cards = $adapter->cardList([
    'page_index' => 1,
    'page_size' => 10,
    'from_created_at' => '2024-01-01 00:00:00',
    'to_created_at' => '2024-12-31 23:59:59',
]);
$detail = $adapter->cardInfo(['card_id' => 'card-id']);
$adapter->cardFreeze(['card_id' => 'card-id']);

// SDK 层 → 官方原始 JSON
$api = $bundle->card();
$raw = $api->pagingVccCard(['pageIndex' => 1, 'pageSize' => 10]);
$newCard = $api->openCard([/* 见 Photonpay 文档 */]);
```

### 异常处理

```php
use Goletter\Card\Platforms\Photonpay\Exceptions\PhotonpayApiException;
use Goletter\Card\Platforms\Photonpay\Exceptions\PhotonpayTokenExpiredException;

try {
    $cards = CardFactory::photonpay($config)->adapter()->cardList([
        'page_index' => 1,
        'page_size' => 10,
    ]);
} catch (PhotonpayTokenExpiredException $e) {
    echo "Token expired: " . $e->getMessage();
} catch (PhotonpayApiException $e) {
    echo "API Error: " . $e->getMessage();
    print_r($e->getResponse());
}
```

## Wasabi API

```php
use Goletter\Card\CardFactory;

$bundle = CardFactory::wasabi([
    'base_url' => 'https://sandbox-api-merchant.wasabicard.com',
    'api_key' => 'your-api-key',
    'private_key' => file_get_contents('/secure/wasabi-private.pem'),
    'wsb_public_key' => file_get_contents('/secure/wasabi-public.pem'),
    'timeout' => 60,
]);

// 适配层
$cards = $bundle->adapter()->cardList(['page_index' => 1, 'page_size' => 20]);
$detail = $bundle->adapter()->cardInfo(['card_id' => 'xxx']);

// SDK 层
$raw = $bundle->card()->listCards(['pageNum' => 1, 'pageSize' => 20]);
```

## Youcardx API

YouCard VCC OpenAPI。联调 `https://test.youcardx.com`，生产 `https://gateway.youcardx.com`。没有统一 Result 适配层，用 SDK 层。Token 用 `client_id` + `secret` 做 Basic 认证自动获取，请求带 HMAC-SHA256 签名。

```php
use Goletter\Card\CardFactory;
use Goletter\Card\Platforms\Youcardx\Exceptions\YoucardxApiException;
use Goletter\Card\Platforms\Youcardx\Exceptions\YoucardxTokenExpiredException;

$bundle = CardFactory::youcardx([
    'base_url' => 'https://test.youcardx.com',
    'client_id' => 'merchant_client_id',
    'secret' => 'merchant_secret',
]);

$cards = $bundle->card()->list(['budget_id' => 'budget_xxx', 'start_page' => 1, 'row' => 20]);
$detail = $bundle->card()->detail('virtual_id');
$bundle->card()->recharge('virtual_id', '10.00', 'order-001');

$bundle->budget()->list(['start_page' => 1, 'row' => 20]);
$bundle->cardholder()->list(['start_page' => 1, 'row' => 20]);
$bundle->account()->balanceList();

$event = $bundle->webhook()->parse(getallheaders(), file_get_contents('php://input'));
```

| 入口 | 说明 |
|------|------|
| `card()` | 开卡、查卡、充值退值、限额、冻结 / 解冻 / 销卡、交易 |
| `budget()` | 预算账户申请、充值转出、结算 |
| `cardholder()` | 持卡人 |
| `file()` | 证件图片上传（jpg/png，不超过 3M） |
| `account()` | 主账户余额与结算 |
| `webhook()` | 验签并解析回调 |

配置项：

| 参数 | 必填 | 默认 | 说明 |
|------|------|------|------|
| `base_url` | 否 | `https://test.youcardx.com` | 也可用 `baseUrl` |
| `client_id` | 是 | — | 商户 client id，也可用 `clientId` |
| `secret` | 是 | — | 签名、Basic 认证、Webhook 验签 |
| `timeout` | 否 | `60` | 秒 |
| `verify_response` | 否 | `true` | 校验响应 `timestamp` / `sign` |
| `access_token` / `expires_in` | 否 | — | 已有 token 时可跳过首次换票 |

成功码是字符串 `0000`。业务 `code` 不是 `0000` 时仍返回响应体，由调用方判断；HTTP 失败抛 `YoucardxApiException`，401 抛 `YoucardxTokenExpiredException`。

```php
try {
    $cards = $bundle->card()->list();
} catch (YoucardxTokenExpiredException $e) {
    // Token 失效，Client 会先刷新并重试一次
} catch (YoucardxApiException $e) {
    $error = $e->getResponse();
}
```

## 调用日志 / 耗时

每次出站 HTTP 请求都会统计耗时（毫秒）。日志不含 Token / API Key / 卡号等敏感字段。

```php
use Goletter\Card\CardFactory;
// 或：use Goletter\Card\Support\CallLog;

CardFactory::setCallLogHandler(function (array $log): void {
    // $log['platform']       slash / airwallex / ...
    // $log['api_interface']  GET:/cards
    // $log['is_success']     1 成功，0 失败
    // $log['error']
    // $log['duration_ms']    请求耗时（毫秒）
    // $log['call_hour']      Y-m-d H:00:00
    // $log['created_at']
});
```

| 开关 | 行为 |
|------|------|
| `log_request=false`（默认） | 失败时若有 `hyperf-utils` 的 `logging()`，写 `call_logs`（含 `duration_ms`） |
| `log_request=true` | 成功/失败都会写 `call_logs`（含 `duration_ms`） |
| `CardFactory::setCallLogHandler` / `CallLog::setHandler` | 有 handler 时每次请求回调（含 `duration_ms`），与上面开关独立 |

配置示例（各平台段均可设；也可用全局环境变量 `CARD_LOG_REQUEST`）：

```php
'slash' => [
    // ...
    'log_request' => (bool) env('SLASH_LOG_REQUEST', env('CARD_LOG_REQUEST', false)),
],
```

```php
// 构造时强制开启
$bundle = CardFactory::slash([
    'api_key' => '...',
    'log_request' => true,
]);
```

未设置 handler 且未开 `log_request`、请求又成功时，不写额外日志。

## 注意事项

### Airwallex

1. **Token 管理**：SDK 会自动管理 token 的获取和刷新。Token 会在过期前 60 秒自动刷新。

2. **环境配置**：
   - 测试环境：`https://api-demo.airwallex.com`
   - 生产环境：`https://api.airwallex.com`

3. **请求来源**：`requestSource` 参数默认为 4，如有特殊要求请根据实际情况调整。

### Slash

1. **API Key 认证**：Slash 使用 API Key 进行认证，通过 `X-API-Key` header 传递。

2. **环境配置**：
   - 生产环境：`https://api.slash.com`
   - 请根据实际情况配置正确的 API 地址

3. **消费限制**：Slash 支持设置消费限制规则，可以基于商户类别、交易金额等进行控制。

### Lampay

1. **认证方式**：`X-Api-Key` + RSA-SHA256 请求签名（`X-Signature`），响应使用平台鉴权公钥验签。

2. **加密**：发卡请求使用 RSA-OAEP-AES-256-GCM 信封；敏感信息与 Webhook 需客户端加密私钥解密。

3. **环境配置**：
   - 生产环境：`https://api.lampay.app`
   - 详见 https://docs.lampay.app/

4. **卡片管理**：列表/详情、加密发卡、消费规则、冻结/解冻/注销、交易查询与 Webhook。

### Photonpay

1. **Token 管理**：未传入 `token` 或临近过期时，会调用 `/api/v1/auth/token`（可用 `token_url` 覆盖）。请配置 `api_key` + `api_secret`。

2. **环境配置**：
   - 默认：`https://x-api.photonpay.com`
   - TLS 校验默认开启，可通过 `verify_ssl => false` 关闭（仅建议本地调试）

3. **卡片功能**：当前适配 `/vcc/openApi/v4/*` 与账户查询接口（`cardList` / `cardInfo` / 冻结解冻 / 交易等）。

### Wasabi

1. **认证**：`X-WSB-API-KEY` + RSA 请求签名；响应用平台公钥验签，敏感数据用商户私钥解密。

2. **环境配置**：沙箱默认 `https://sandbox-api-merchant.wasabicard.com`，生产请换成正式域名。

### Youcardx

1. **认证**：`GET /api/v2/vcc/token`，`Authorization: Basic base64(client_id:secret)`。再次换票会使旧 token 失效，可用 `token_loader` / `token_saver` 在进程间共用。
2. **签名**：请求头 `timestamp` + `sign`（HMAC-SHA256，密钥为 `secret`）。Webhook 签名串是 `{MessageType}.{Timestamp}.{rawBody}`。
3. **环境**：联调 `https://test.youcardx.com`，生产 `https://gateway.youcardx.com`。
4. **异步**：开卡、充值、冻结等结果通过 Webhook 回调。商户需在 10 秒内返回 HTTP 200。

### 通用

1. **请求超时**：默认超时时间为 60 秒，可根据实际情况调整。

2. **请求耗时**：各平台支持 `log_request`；详见上文「调用日志 / 耗时」。

3. **错误处理**：建议在生产环境中对异常进行适当的处理和日志记录。

4. **API 文档**：具体 API 字段和参数请参考各平台的官方 API 文档。

## 许可证

MIT
