<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/url.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/stock.php';
require_once __DIR__ . '/../includes/pdf.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/format.php';
require_once __DIR__ . '/../includes/quotes.php';
require_once __DIR__ . '/../includes/signature.php';

start_session();

$page = $_GET['page'] ?? '';
$export_action = $_GET['export'] ?? '';

// --- PDF Export Handling ---
// Handle all PDF exports here, before any HTML is rendered.
if ($export_action === 'pdf') {
    require_login();
    $id = (int)($_GET['id'] ?? 0);
    
    // Determine which PDF to render based on the page
    switch ($page) {
        case 'invoice':
            require __DIR__ . '/../views/invoice.php';
            break;
        case 'purchase_view': // Use a consistent name like 'purchase'
            require __DIR__ . '/../views/purchase_view.php';
            break;
        case 'quote_view':
            require __DIR__ . '/../views/quote_view.php';
            break;
        // You can add cases for reports here as well if needed
    }
    // The required file will call render_pdf() and then exit, so no more code runs.
    exit;
}

// Handle other actions that require an immediate redirect
if ($page === 'logout') {
    auth_logout();
    redirect_to_page('login');
    exit;
}

if ($page === 'convert_quote_to_sale') {
    require_login();
    csrf_verify_get();
    $id = (int)($_GET['id'] ?? 0);
    $sale_id = convert_quote_to_sale($id);
    if ($sale_id) {
        redirect_to_page('invoice', ['id' => $sale_id]);
    } else {
        redirect_to_page('quotes', ['msg' => 'Conversion failed']);
    }
    exit;
}

if ($page === 'save_signature') {
    require_login();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        $id = (int)($_GET['id'] ?? 0);
        $result = false;
        if (!empty($_FILES['signature_file'])) {
            $result = save_digital_signature($id, null, $_FILES['signature_file']);
        } else {
            $data = $_POST['signature'] ?? '';
            $result = save_digital_signature($id, $data);
        }
        http_response_code($result ? 200 : 500);
        echo $result ? 'Signature saved.' : 'Failed to save signature.';
    }
    exit;
}

if ($page === 'delete_signature') {
    require_login();
    csrf_verify_get();
    $id = (int)($_GET['id'] ?? 0);
    delete_digital_signature($id);
    redirect_to_page('quote_view', ['id' => $id]);
    exit;
}

if (is_setup_required() && !in_array($page, ['setup', 'login'])) {
    redirect_to_page('setup');
    exit;
}

// Enforce permissions (after login) for all pages except login/setup
if (!in_array($page, ['', 'login', 'setup'], true)) {
    require_login();
    require_page_permission($page);
}

// Regular page routing
$page_path = __DIR__ . '/../views/' . $page . '.php';

if (file_exists($page_path)) {
    require $page_path;
} else {
    // Fallback for master data pages and dashboard
    switch ($page) {
        case 'categories':
        case 'units':
        case 'locations':
        case 'suppliers':
        case 'customers':
            require __DIR__ . '/../views/master.php';
            break;
        default:
            require __DIR__ . '/../views/dashboard.php';
            break;
    }
}