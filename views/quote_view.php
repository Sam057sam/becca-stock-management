<?php
require_once __DIR__ . '/partials/header.php';
$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);
$quote = $pdo->prepare('SELECT q.*, COALESCE(c.name, "Walk-in") AS customer,
  c.address_line, c.address_line_2, c.landmark, c.city, c.state, c.zipcode, c.country
  FROM quotes q LEFT JOIN customers c ON c.id=q.customer_id WHERE q.id=?');
$quote->execute([$id]);
$quote = $quote->fetch();
if (!$quote) { echo '<p>Quote not found.</p>'; require __DIR__.'/partials/footer.php'; exit; }
$items = $pdo->prepare('SELECT qi.*, p.name, l.name AS location FROM quote_items qi JOIN products p ON p.id=qi.product_id JOIN locations l ON l.id=qi.location_id WHERE quote_id=?');
$items->execute([$id]);
$items = $items->fetchAll();
// Correctly call format_address with an array
$customer_address = format_address($quote);

// PDF export if requested
if (($_GET['export'] ?? '') === 'pdf') {
  ob_start();
  ?>
  <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#111">
    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:10px">
      <div style="display:flex;gap:12px;align-items:center">
        <?php if (is_file(__DIR__.'/../public/assets/logo.png')): ?>
          <img src="<?= __DIR__.'/../public/assets/logo.png' ?>" style="height:54px;width:auto">
        <?php endif; ?>
        <div>
          <h2 style="margin:0">Quote/Estimate</h2>
          <div>No: <?= htmlspecialchars($quote['quote_no']) ?></div>
          <div>Date: <?= htmlspecialchars($quote['quote_date']) ?></div>
          <?php if ($quote['valid_until']): ?><div>Valid Until: <?= htmlspecialchars($quote['valid_until']) ?></div><?php endif; ?>
          <?php if (table_exists('settings')): ?>
            <div><strong><?= htmlspecialchars(setting('company_name','')) ?></strong></div>
            <div style="white-space:pre-wrap;max-width:260px;"><?= htmlspecialchars(format_address(settings_all(), 'company_')) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <div style="text-align:right">
        <div><strong>Customer</strong></div>
        <div><?= htmlspecialchars($quote['customer']) ?></div>
        <div style="max-width:240px;white-space:pre-wrap"><?= htmlspecialchars($customer_address) ?></div>
      </div>
    </div>
    <table style="width:100%;border-collapse:collapse" border="1" cellpadding="4">
      <tr><th>#</th><th>Product</th><th>Location</th><th>Qty</th><th>Price</th><th>Total</th></tr>
      <?php $i=1; foreach ($items as $it): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= htmlspecialchars($it['name']) ?></td>
          <td><?= htmlspecialchars($it['location']) ?></td>
          <td><?= (float)$it['quantity'] ?></td>
          <td><?= money((float)$it['unit_price']) ?></td>
          <td><?= money((float)$it['line_total']) ?></td>
        </tr>
      <?php endforeach; ?>
      <tr><td colspan="5" align="right">Subtotal</td><td><?= money((float)$quote['subtotal']) ?></td></tr>
      <tr><td colspan="5" align="right">Discount</td><td><?= money((float)$quote['discount']) ?></td></tr>
      <tr><td colspan="5" align="right">Tax</td><td><?= money((float)$quote['tax']) ?></td></tr>
      <tr><td colspan="5" align="right"><strong>Total</strong></td><td><strong><?= money((float)$quote['total']) ?></strong></td></tr>
    </table>
    <?php if (!empty($quote['signature_path'])): ?>
        <p style="margin-top:20px;"><strong>Approved By Signature:</strong></p>
        <img src="<?= __DIR__.'/../public/'.$quote['signature_path'] ?>" style="max-width:300px; height:auto; border:1px solid #ccc;"/>
    <?php endif; ?>
  </div>
  <?php
  $html = ob_get_clean();
  render_pdf($html, 'quote-'.$quote['quote_no'].'.pdf');
}
?>
<div class="p-6">
  <div class="max-w-4xl mx-auto bg-white p-8 border border-gray-200 rounded-lg shadow-sm">
    <div class="flex justify-between items-start mb-6">
      <div class="flex items-center gap-4">
        <?php if (is_file(__DIR__.'/../public/assets/logo.png')): ?>
          <img src="assets/logo.png" alt="Logo" class="h-16 w-auto">
        <?php endif; ?>
        <div>
          <h2 class="text-2xl font-bold">Quote/Estimate</h2>
          <div>No: <span class="text-gray-800"><?= htmlspecialchars($quote['quote_no']) ?></span></div>
          <div>Date: <span class="text-gray-800"><?= htmlspecialchars($quote['quote_date']) ?></span></div>
          <?php if ($quote['valid_until']): ?><div>Valid Until: <span class="text-gray-800"><?= htmlspecialchars($quote['valid_until']) ?></span></div><?php endif; ?>
        </div>
      </div>
      <div class="text-right text-sm">
         <div class="font-semibold text-gray-700">Customer</div>
        <div class="text-gray-800"><?= htmlspecialchars($quote['customer']) ?></div>
        <div class="text-xs text-gray-500 whitespace-pre-wrap"><?= htmlspecialchars($customer_address) ?></div>
      </div>
    </div>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">#</th>
                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">Product</th>
                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">Location</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase">Qty</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase">Price</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase">Total</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <?php $i=1; foreach ($items as $it): ?>
            <tr>
              <td class="px-3 py-2"><?= $i++ ?></td>
              <td class="px-3 py-2"><?= htmlspecialchars($it['name']) ?></td>
              <td class="px-3 py-2"><?= htmlspecialchars($it['location']) ?></td>
              <td class="px-3 py-2 text-right"><?= (float)$it['quantity'] ?></td>
              <td class="px-3 py-2 text-right"><?= money((float)$it['unit_price']) ?></td>
              <td class="px-3 py-2 text-right"><?= money((float)$it['line_total']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-gray-50">
            <tr><td colspan="5" class="px-3 py-2 text-right font-semibold">Subtotal</td><td class="px-3 py-2 text-right"><?= money((float)$quote['subtotal']) ?></td></tr>
            <tr><td colspan="5" class="px-3 py-2 text-right font-semibold">Discount</td><td class="px-3 py-2 text-right"><?= money((float)$quote['discount']) ?></td></tr>
            <tr><td colspan="5" class="px-3 py-2 text-right font-semibold">Tax</td><td class="px-3 py-2 text-right"><?= money((float)$quote['tax']) ?></td></tr>
            <tr><td colspan="5" class="px-3 py-2 text-right font-bold text-base">Total</td><td class="px-3 py-2 text-right font-bold text-base"><?= money((float)$quote['total']) ?></strong></td></tr>
        </tfoot>
    </table>
    
    <div class="mt-8">
        <?php if (empty($quote['signature_path'])): ?>
            <button class="rounded-md bg-gray-100 px-3 py-2 text-sm" onclick="openSignatureModal()">Add Signature</button>
        <?php else: ?>
            <p class="text-sm font-semibold mb-2">Quote Approved:</p>
            <img src="<?= htmlspecialchars(base_url($quote['signature_path'])) ?>" alt="Customer Signature" class="max-w-xs border border-gray-300 rounded"/>
            <p class="text-xs text-gray-500 mt-1">Signature added at: <?= htmlspecialchars($quote['signed_at']) ?></p>
            <a href="<?= page_url('delete_signature', ['id' => $quote['id'], '_token' => csrf_token()]) ?>" onclick="return confirm('Are you sure?')" class="mt-2 rounded-md bg-red-500 text-white px-3 py-1 text-xs inline-block">Delete Signature</a>
        <?php endif; ?>
    </div>

    <div class="mt-8 flex justify-between">
      <a class="btn-secondary rounded-md px-4 py-2 text-sm" href="<?= page_url('quotes') ?>">Back to Quotes</a>
      <div>
        <a class="btn-secondary rounded-md px-4 py-2 text-sm" href="<?= page_url('quote_view', ['id' => $quote['id'], 'export' => 'pdf']) ?>">Export PDF</a>
        <a class="rounded-md bg-[var(--primary-color)] text-white px-4 py-2 text-sm font-semibold" href="<?= page_url('convert_quote_to_sale', ['id' => $quote['id'], '_token' => csrf_token()]) ?>">Convert to Sale</a>
      </div>
    </div>
  </div>
</div>

<div id="signatureModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
  <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
    <div class="mt-3 text-center">
      <h3 class="text-lg leading-6 font-medium text-gray-900">Add Signature</h3>
      <div class="mt-2 px-7 py-3">
        <div id="signatureMethodTabs" class="flex justify-center mb-4">
          <button id="drawTab" class="px-4 py-2 text-sm font-medium border-b-2 border-[var(--primary-color)]">Draw Signature</button>
          <button id="uploadTab" class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500">Upload Image</button>
        </div>
        <div id="drawSection">
          <canvas id="signatureCanvas" class="border border-gray-400 rounded-md bg-white"></canvas>
        </div>
        <div id="uploadSection" class="hidden">
          <form id="uploadForm" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <label for="signatureFile" class="block text-left text-sm font-medium text-gray-700">Select an image file:</label>
            <input type="file" id="signatureFile" name="signature_file" accept="image/png, image/jpeg" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"/>
          </form>
        </div>
      </div>
      <div class="items-center px-4 py-3">
        <button id="clearBtn" class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md w-24 mr-2">Clear</button>
        <button id="saveBtn" class="px-4 py-2 bg-[var(--primary-color)] text-white text-base font-medium rounded-md w-24">Save</button>
      </div>
    </div>
  </div>
</div>

<script>
let canvas, ctx, isDrawing = false;
let lastX = 0;
let lastY = 0;
let currentMethod = 'draw';
let rect;

function setupCanvasListeners() {
    canvas.addEventListener('mousedown', drawStart);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', drawEnd);
    canvas.addEventListener('mouseleave', drawEnd);
    canvas.addEventListener('touchstart', drawStart, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', drawEnd);
}

function draw(e) {
    if (!isDrawing) return;
    e.preventDefault();
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    const {x, y} = getMousePos(e);
    ctx.lineTo(x, y);
    ctx.stroke();
    [lastX, lastY] = [x, y];
}

function drawStart(e) {
    isDrawing = true;
    const {x, y} = getMousePos(e);
    [lastX, lastY] = [x, y];
    e.preventDefault();
}
function drawEnd(e) {
    isDrawing = false;
    e.preventDefault();
}

function getMousePos(e) {
    rect = canvas.getBoundingClientRect();
    const clientX = e.clientX || e.touches[0].clientX;
    const clientY = e.clientY || e.touches[0].clientY;
    return {
        x: clientX - rect.left,
        y: clientY - rect.top
    };
}

function openSignatureModal() {
    document.getElementById('signatureModal').style.display = 'block';
    canvas = document.getElementById('signatureCanvas');
    ctx = canvas.getContext('2d');
    canvas.width = 300;
    canvas.height = 150;
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#000';
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    isDrawing = false;
    setupCanvasListeners();
}

document.getElementById('signatureModal').addEventListener('click', (e) => {
    if (e.target.id === 'signatureModal') {
        document.getElementById('signatureModal').style.display = 'none';
    }
});
document.getElementById('clearBtn').addEventListener('click', () => {
    if (currentMethod === 'draw') {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        isDrawing = false;
    } else {
        document.getElementById('uploadForm').reset();
    }
});
document.getElementById('saveBtn').addEventListener('click', () => {
    if (currentMethod === 'draw') {
        if (isCanvasBlank(canvas)) {
            alert('Please provide a signature first.');
            return;
        }
        const dataURL = canvas.toDataURL('image/png');
        saveSignature(dataURL, 'base64');
    } else {
        const fileInput = document.getElementById('signatureFile');
        if (fileInput.files.length === 0) {
            alert('Please select a file to upload.');
            return;
        }
        saveSignature(fileInput.files[0], 'file');
    }
});

function isCanvasBlank(canvas) {
    const context = canvas.getContext('2d');
    const pixelBuffer = new Uint32Array(context.getImageData(0, 0, canvas.width, canvas.height).data.buffer);
    return !pixelBuffer.some(color => color !== 0);
}

function saveSignature(data, method) {
    const quoteId = <?= json_encode($id) ?>;
    const token = document.querySelector('input[name="_token"]').value;

    const xhr = new XMLHttpRequest();
    xhr.onload = function() {
        if (this.status >= 200 && this.status < 300) {
            alert('Signature saved successfully!');
            location.reload();
        } else {
            alert('Failed to save signature. ' + this.responseText);
        }
    };
    
    xhr.open('POST', `<?= page_url('save_signature') ?>&id=${quoteId}`, true);

    if (method === 'base64') {
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send(`_token=${token}&signature=${encodeURIComponent(data)}`);
    } else {
        const formData = new FormData();
        formData.append('_token', token);
        formData.append('signature_file', data);
        xhr.send(formData);
    }
}

document.getElementById('drawTab').addEventListener('click', () => {
    currentMethod = 'draw';
    document.getElementById('drawSection').classList.remove('hidden');
    document.getElementById('uploadSection').classList.add('hidden');
    document.getElementById('drawTab').classList.add('border-[var(--primary-color)]');
    document.getElementById('drawTab').classList.remove('border-transparent', 'text-gray-500');
    document.getElementById('uploadTab').classList.add('border-transparent', 'text-gray-500');
    document.getElementById('uploadTab').classList.remove('border-[var(--primary-color)]');
});

document.getElementById('uploadTab').addEventListener('click', () => {
    currentMethod = 'upload';
    document.getElementById('drawSection').classList.add('hidden');
    document.getElementById('uploadSection').classList.remove('hidden');
    document.getElementById('uploadTab').classList.add('border-[var(--primary-color)]');
    document.getElementById('uploadTab').classList.remove('border-transparent', 'text-gray-500');
    document.getElementById('drawTab').classList.add('border-transparent', 'text-gray-500');
    document.getElementById('drawTab').classList.remove('border-[var(--primary-color)]');
});
</script>
<?php require_once __DIR__ . '/partials/footer.php'; ?>