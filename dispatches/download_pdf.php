<?php
// api/v1/dispatches/download_pdf.php
require_once '../db_connect.php';
require_once '../lib/fpdf/fpdf.php';

if (!isset($_GET['id'])) {
    http_response_code(400);
    die('Dispatch ID is required.');
}
$dispatch_id = (int)$_GET['id'];

// --- Fetch Data (This part is unchanged) ---
$response = [];
$stmt_dispatch = $conn->prepare("SELECT * FROM dispatches WHERE id = ?");
$stmt_dispatch->bind_param("i", $dispatch_id);
$stmt_dispatch->execute();
$response['dispatch_details'] = $stmt_dispatch->get_result()->fetch_assoc();

if (!$response['dispatch_details']) {
    http_response_code(404);
    die('Dispatch not found.');
}

$stmt_items = $conn->prepare("
    SELECT c.client_name, mi.item_name, di.quantity_dispatched, re.receiving_description_override as description
    FROM dispatch_items di
    JOIN receiving_events re ON di.receiving_event_id = re.id
    JOIN order_items oi ON di.order_item_id = oi.id
    JOIN master_items mi ON oi.master_item_id = mi.id
    JOIN orders o ON oi.order_id = o.id
    JOIN clients c ON o.client_id = c.id
    WHERE di.dispatch_id = ?
    ORDER BY c.client_name, mi.item_name
");
$stmt_items->bind_param("i", $dispatch_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();

$items_by_customer = [];
while ($row = $result_items->fetch_assoc()) {
    $items_by_customer[$row['client_name']][] = $row;
}
$response['items_by_customer'] = $items_by_customer;
$conn->close();

// --- Generate PDF ---
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);

// Header
$pdf->Cell(0, 10, 'Delivery Challan', 0, 1, 'C');
$pdf->Ln(10);

// --- UPDATED Main Dispatch Details section ---
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(40, 7, 'Dispatch ID:', 0, 0, 'L');
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(95, 7, 'D' . $response['dispatch_details']['id'], 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(30, 7, 'Dispatch Date:', 0, 0, 'R');
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(25, 7, $response['dispatch_details']['dispatch_date'], 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(40, 7, 'Tracking Number:', 0, 0, 'L');
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(95, 7, $response['dispatch_details']['tracking_number'] ?: 'N/A', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(30, 7, 'Mode:', 0, 0, 'R');
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(25, 7, $response['dispatch_details']['mode_of_dispatch'], 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(40, 7, 'Amount Paid:', 0, 0, 'L');
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, 'Rs. ' . number_format($response['dispatch_details']['dispatch_amount'] ?: 0, 2), 0, 1, 'L');
    
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(40, 7, 'Comments:', 0, 0, 'L');
$pdf->SetFont('Arial', '', 11);
$pdf->MultiCell(0, 7, $response['dispatch_details']['comments'] ?: 'N/A', 0, 'L');
$pdf->Ln(10);

// --- UPDATED Items Dispatched section ---
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Items Dispatched', 0, 1);

foreach ($response['items_by_customer'] as $customer => $items) {
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, $customer, 'B', 1);
    
    // Item Table Header
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(60, 7, 'Item', 1); // Reduced width
    $pdf->Cell(20, 7, 'Quantity', 1);
    $pdf->Cell(110, 7, 'Description', 1); // Increased width
    $pdf->Ln();
    
    // Item Rows
    $pdf->SetFont('Arial', '', 10);
    foreach ($items as $item) {
        $pdf->Cell(60, 7, $item['item_name'], 1); // Reduced width
        $pdf->Cell(20, 7, $item['quantity_dispatched'], 1, 0, 'C');
        $pdf->Cell(110, 7, $item['description'] ?: 'N/A', 1); // Increased width
        $pdf->Ln();
    }
    $pdf->Ln(5);
}

// Output the PDF
$pdf->Output('D', 'challan_D' . $dispatch_id . '.pdf');
exit;
?>