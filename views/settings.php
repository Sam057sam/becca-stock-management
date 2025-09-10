<?php
require_role(['Admin']);
require_once __DIR__ . '/partials/header.php';

$pdo = get_db();
if (!table_exists('settings')) {
  echo '<div class="p-6"><div class="note">The settings table does not exist. Please run the database migrations to enable this page.</div></div>';
  require_once __DIR__ . '/partials/footer.php';
  exit;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    settings_set([
        'company_name' => trim($_POST['company_name'] ?? ''),
        'company_phone' => trim($_POST['company_phone'] ?? ''),
        'company_email' => trim($_POST['company_email'] ?? ''),
        'company_address_line_1' => trim($_POST['company_address_line_1'] ?? ''),
        'company_address_line_2' => trim($_POST['company_address_line_2'] ?? ''),
        'company_landmark' => trim($_POST['company_landmark'] ?? ''),
        'company_city' => trim($_POST['company_city'] ?? ''),
        'company_state' => trim($_POST['company_state'] ?? ''),
        'company_zipcode' => trim($_POST['company_zipcode'] ?? ''),
        'company_gstin' => trim($_POST['company_gstin'] ?? ''),
        'currency_symbol' => trim($_POST['currency_symbol'] ?? ''),
        'currency_code' => trim($_POST['currency_code'] ?? ''),
    ]);
    $msg = 'Settings saved successfully.';
}

// Fetch all settings for the form
$name = setting('company_name', '');
$phone = setting('company_phone', '');
$email = setting('company_email', '');
$addr1 = setting('company_address_line_1', '');
$addr2 = setting('company_address_line_2', '');
$landmark = setting('company_landmark', '');
$city = setting('company_city', '');
$state = setting('company_state', '');
$zipcode = setting('company_zipcode', '');
$gstin = setting('company_gstin', '');
$currency_symbol = setting('currency_symbol', '$');
$currency_code = setting('currency_code', 'USD');

?>
<div class="p-6">
  <div class="mx-auto max-w-4xl">
    <h2 class="text-2xl font-semibold mb-4">Application Settings</h2>
    <?php if ($msg): ?><div class="note mb-4"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm mb-6">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-semibold">Company Details</h3>
                <p class="text-sm text-gray-500">This information will appear on your invoices and quotes.</p>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label for="company_name" class="block text-sm font-medium text-gray-700">Company Name</label>
                    <input type="text" id="company_name" name="company_name" value="<?= htmlspecialchars($name) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                </div>
                <div>
                    <label for="company_phone" class="block text-sm font-medium text-gray-700">Phone</label>
                    <input type="text" id="company_phone" name="company_phone" value="<?= htmlspecialchars($phone) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label for="company_email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" id="company_email" name="company_email" value="<?= htmlspecialchars($email) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div class="md:col-span-2">
                    <label for="company_address_line_1" class="block text-sm font-medium text-gray-700">Address Line 1</label>
                    <input type="text" id="company_address_line_1" name="company_address_line_1" value="<?= htmlspecialchars($addr1) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                 <div class="md:col-span-2">
                    <label for="company_address_line_2" class="block text-sm font-medium text-gray-700">Address Line 2</label>
                    <input type="text" id="company_address_line_2" name="company_address_line_2" value="<?= htmlspecialchars($addr2) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                 <div>
                    <label for="company_landmark" class="block text-sm font-medium text-gray-700">Landmark</label>
                    <input type="text" id="company_landmark" name="company_landmark" value="<?= htmlspecialchars($landmark) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label for="company_city" class="block text-sm font-medium text-gray-700">City</label>
                    <input type="text" id="company_city" name="company_city" value="<?= htmlspecialchars($city) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label for="company_state" class="block text-sm font-medium text-gray-700">State</label>
                    <input type="text" id="company_state" name="company_state" value="<?= htmlspecialchars($state) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label for="company_zipcode" class="block text-sm font-medium text-gray-700">Pincode / Zip Code</label>
                    <input type="text" id="company_zipcode" name="company_zipcode" value="<?= htmlspecialchars($zipcode) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg shadow-sm mb-6">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-semibold">Tax & Currency</h3>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="company_gstin" class="block text-sm font-medium text-gray-700">Company GSTIN</label>
                    <input type="text" id="company_gstin" name="company_gstin" value="<?= htmlspecialchars($gstin) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div></div>
                <div>
                    <label for="currency_symbol" class="block text-sm font-medium text-gray-700">Currency Symbol</label>
                    <input type="text" id="currency_symbol" name="currency_symbol" value="<?= htmlspecialchars($currency_symbol) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label for="currency_code" class="block text-sm font-medium text-gray-700">Currency Code</label>
                    <input type="text" id="currency_code" name="currency_code" value="<?= htmlspecialchars($currency_code) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="e.g., USD, INR">
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-4">
            <button type="submit" class="rounded-md border border-transparent bg-[var(--primary-color)] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-opacity-90">Save Settings</button>
        </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>