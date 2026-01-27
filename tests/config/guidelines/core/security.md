# Magento 2 Security Guidelines

## Input Validation

### Always Validate Input

Never trust user input. Validate and sanitize all data:

```php
// Use type hints for automatic validation
public function process(int $productId, string $sku): void

// For arrays, validate each element
foreach ($items as $item) {
    if (!is_array($item) || !isset($item['sku'])) {
        throw new InputException(__('Invalid item format'));
    }
}
```

### Use Magento's Validation

```php
use Magento\Framework\Validator\EmailAddress;

$validator = new EmailAddress();
if (!$validator->isValid($email)) {
    throw new InputException(__('Invalid email address'));
}
```

## SQL Injection Prevention

### Use Parameterized Queries

```php
// CORRECT - Use bind parameters
$connection->select()
    ->from($table)
    ->where('sku = ?', $sku)
    ->where('store_id IN (?)', $storeIds);

// WRONG - Never concatenate user input
$connection->query("SELECT * FROM products WHERE sku = '$sku'");
```

### Use Collections and Repositories

Prefer Magento's abstraction layers:

```php
$searchCriteria = $this->searchCriteriaBuilder
    ->addFilter('sku', $sku)
    ->addFilter('status', Status::STATUS_ENABLED)
    ->create();

$products = $this->productRepository->getList($searchCriteria);
```

## XSS Prevention

### Escape Output

Always escape data before rendering:

```php
// In templates
<?= $block->escapeHtml($value) ?>
<?= $block->escapeHtmlAttr($attribute) ?>
<?= $block->escapeUrl($url) ?>
<?= $block->escapeJs($jsValue) ?>
```

### Never Use Raw Output

```php
// WRONG
<?= $rawHtml ?>

// CORRECT - Only when HTML is intentional and sanitized
<?= /* @noEscape */ $this->getSafeHtml() ?>
```

## CSRF Protection

### Form Keys

All forms must include a form key:

```php
// In templates
<form action="<?= $block->escapeUrl($block->getFormAction()) ?>" method="post">
    <?= $block->getBlockHtml('formkey') ?>
    <!-- form fields -->
</form>
```

### Validate Form Keys

```php
public function execute(): ResultInterface
{
    if (!$this->formKeyValidator->validate($this->getRequest())) {
        throw new LocalizedException(__('Invalid form key'));
    }
    // Process form
}
```

## Authentication & Authorization

### ACL Resources

Define ACL resources in `etc/acl.xml`:

```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Acl/etc/acl.xsd">
    <acl>
        <resources>
            <resource id="Magento_Backend::admin">
                <resource id="Vendor_Module::config" title="Module Configuration"/>
            </resource>
        </resources>
    </acl>
</config>
```

### Check Authorization

```php
public function execute(): ResultInterface
{
    if (!$this->authorization->isAllowed('Vendor_Module::config')) {
        throw new AuthorizationException(__('Access denied'));
    }
    // Process request
}
```

### API Authentication

Configure in `etc/webapi.xml`:

```xml
<route url="/V1/custom/resource" method="POST">
    <service class="Vendor\Module\Api\ServiceInterface" method="process"/>
    <resources>
        <resource ref="Vendor_Module::manage"/>
    </resources>
</route>
```

## Sensitive Data

### Encrypt Sensitive Values

```php
// Store encrypted
$encryptor = $this->encryptor;
$encrypted = $encryptor->encrypt($sensitiveValue);

// Retrieve decrypted
$decrypted = $encryptor->decrypt($encrypted);
```

### Mask in Logs

```php
$this->logger->info('Processing payment', [
    'order_id' => $orderId,
    'amount' => $amount,
    // Never log card numbers, passwords, tokens
]);
```

## File Upload Security

### Validate File Types

```php
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($file->getMimeType(), $allowedTypes, true)) {
    throw new LocalizedException(__('Invalid file type'));
}
```

### Use Secure Paths

```php
// Store uploads outside webroot or in protected directories
$targetPath = $this->directoryList->getPath(DirectoryList::MEDIA)
    . '/custom/uploads/';
```

## Security Headers

Configure in server or via controller:

```php
$response->setHeader('X-Content-Type-Options', 'nosniff');
$response->setHeader('X-Frame-Options', 'SAMEORIGIN');
$response->setHeader('X-XSS-Protection', '1; mode=block');
```
