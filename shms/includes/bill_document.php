<?php

function shmsBillNumber($id) { return 'SHMS-' . str_pad((string)(int)$id, 5, '0', STR_PAD_LEFT); }
function shmsBillDate($value) { return $value ? date('d M Y', strtotime($value)) : 'Not set'; }

function shmsLoadBill($id, $residentId = null) {
    $sql = "SELECT b.*, r.name AS resident_name, r.email, r.phone, rm.room_number, rt.type_name
            FROM bills b JOIN residents r ON r.id=b.resident_id
            LEFT JOIN room_allocations ra ON ra.resident_id=r.id AND ra.is_active=1
            LEFT JOIN rooms rm ON rm.id=ra.room_id
            LEFT JOIN room_types rt ON rt.id=rm.room_type_id WHERE b.id=?";
    $params = [(int)$id];
    if ($residentId !== null) { $sql .= ' AND b.resident_id=?'; $params[] = (int)$residentId; }
    $rows = shmsRows($sql . ' LIMIT 1', $params);
    return $rows ? $rows[0] : null;
}

function shmsBillMessDetails($billId) {
    if (!shmsTableExists('bill_mess_details')) { return []; }
    return shmsRows('SELECT attendance_date,meal_type,charged_rate FROM bill_mess_details WHERE bill_id=? ORDER BY attendance_date,FIELD(meal_type,\'breakfast\',\'lunch\',\'snacks\',\'dinner\')', [(int)$billId]);
}

function shmsBillPdf($bill) {
    if (!function_exists('mb_substr')) { throw new InvalidArgumentException('PDF bills require PHP mbstring. Enable extension=mbstring in php.ini and restart Apache.'); }
    require_once __DIR__ . '/../vendor/tfpdf/font/unifont/ttfonts.php';
    require_once __DIR__ . '/../vendor/tfpdf/tfpdf.php';

    $details = shmsBillMessDetails($bill['id']);
    $mealSummary = [];
    foreach ($details as $detail) {
        $meal = $detail['meal_type']; $rate = (float)$detail['charged_rate'];
        if (!isset($mealSummary[$meal])) { $mealSummary[$meal] = ['qty'=>0,'rates'=>[],'amount'=>0.0]; }
        $mealSummary[$meal]['qty']++;
        $mealSummary[$meal]['rates'][number_format($rate,2,'.','')] = true;
        $mealSummary[$meal]['amount'] += $rate;
    }

    // Landscape A4 allows two equal voucher copies side by side.
    $pdf = new tFPDF('L', 'mm', 'A4');
    $pdf->SetTitle('Bill ' . shmsBillNumber($bill['id']));
    $pdf->SetAuthor(APP_NAME);
    $pdf->SetMargins(7, 7, 7); $pdf->SetAutoPageBreak(false); $pdf->AddPage();
    $pdf->AddFont('DejaVu', '', 'DejaVuSansCondensed.ttf', true);
    $pdf->AddFont('DejaVu', 'B', 'DejaVuSansCondensed-Bold.ttf', true);

    $hasDetails = !empty($details);
    $drawCopy = static function (float $x, string $copyTitle) use ($pdf, $bill, $mealSummary, $hasDetails): void {
        $y = 7.0; $w = 139.0; $inner = $w - 10;
        $pdf->SetDrawColor(90,90,90); $pdf->Rect($x,$y,$w,195);
        $pdf->SetFillColor(52,58,64); $pdf->Rect($x,$y,$w,22,'F');
        $pdf->SetTextColor(255,255,255); $pdf->SetFont('DejaVu','B',14);
        $pdf->SetXY($x+5,$y+4); $pdf->Cell($inner,6,APP_NAME,0,1,'C');
        $pdf->SetX($x+5); $pdf->SetFont('DejaVu','B',9); $pdf->Cell($inner,6,strtoupper($copyTitle),0,1,'C');

        $pdf->SetTextColor(33,37,41); $pdf->SetXY($x+5,$y+27); $pdf->SetFont('DejaVu','B',9);
        $pdf->Cell(64,6,'Bill # '.shmsBillNumber($bill['id']),0,0);
        $pdf->Cell(65,6,monthName($bill['month']).' '.$bill['year'],0,1,'R');
        $pdf->SetX($x+5); $pdf->SetFont('DejaVu','',8.5);
        $pdf->Cell(64,6,'Resident: '.mb_substr((string)$bill['resident_name'],0,28),0,0);
        $pdf->Cell(65,6,'Room: '.($bill['room_number'] ?: 'Not allocated'),0,1,'R');
        $pdf->SetX($x+5); $pdf->Cell(64,6,'Generated: '.shmsBillDate($bill['generated_at']),0,0);
        $pdf->Cell(65,6,'Due: '.shmsBillDate($bill['due_date']),0,1,'R');

        $pdf->SetX($x+5); $pdf->SetFillColor(52,58,64); $pdf->SetTextColor(255,255,255); $pdf->SetFont('DejaVu','B',8.5);
        $pdf->Cell(88,7,'Bill Description',1,0,'L',true); $pdf->Cell(41,7,'Amount (PKR)',1,1,'R',true);
        $items = [
            'Room Rent' => $bill['room_rent'],
            'Previous Unpaid Dues' => $bill['previous_dues'] ?? 0,
            'Miscellaneous Charges' => $bill['misc_charges'],
            'Mess Charges' => $bill['mess_charges'],
        ];
        $pdf->SetTextColor(33,37,41); $pdf->SetFont('DejaVu','',8.5);
        foreach ($items as $label=>$amount) {
            $pdf->SetX($x+5); $pdf->Cell(88,7,$label,1,0); $pdf->Cell(41,7,number_format((float)$amount,2),1,1,'R');
        }
        $pdf->SetX($x+5); $pdf->SetFillColor(233,236,239); $pdf->SetFont('DejaVu','B',9.5);
        $pdf->Cell(88,8,'TOTAL AMOUNT',1,0,'R',true); $pdf->Cell(41,8,number_format((float)$bill['total_amount'],2),1,1,'R',true);

        if ($hasDetails) {
            $pdf->SetX($x+5); $pdf->SetFont('DejaVu','B',9); $pdf->Cell($inner,7,'Mess Charge Summary',0,1);
            $pdf->SetX($x+5); $pdf->SetFillColor(80,86,92); $pdf->SetTextColor(255,255,255); $pdf->SetFont('DejaVu','B',7.5);
            $pdf->Cell(43,6,'Meal',1,0,'L',true); $pdf->Cell(20,6,'Qty',1,0,'C',true); $pdf->Cell(32,6,'Rate',1,0,'R',true); $pdf->Cell(34,6,'Amount',1,1,'R',true);
            $pdf->SetTextColor(33,37,41); $pdf->SetFont('DejaVu','',7.5);
            foreach ($mealSummary as $meal=>$summary) {
                $rates = array_keys($summary['rates']);
                $rateText = count($rates) === 1 ? $rates[0] : (count($rates) > 1 ? 'Varied' : '—');
                $pdf->SetX($x+5); $pdf->Cell(43,6,ucfirst($meal),1,0); $pdf->Cell(20,6,(string)$summary['qty'],1,0,'C');
                $pdf->Cell(32,6,$rateText,1,0,'R'); $pdf->Cell(34,6,number_format((float)$summary['amount'],2),1,1,'R');
            }
        } elseif ((float)$bill['mess_charges'] > 0) {
            $pdf->SetX($x+5); $pdf->SetFont('DejaVu','',7.5);
            $pdf->MultiCell($inner,5,'Mess line-items are unavailable because this bill was generated before the billing-integrity update.',0,'L');
        }

        $pdf->SetX($x+5); $pdf->SetFont('DejaVu','B',8.5); $pdf->Cell(65,8,'Payment: '.ucfirst($bill['payment_status']),0,0);
        $pdf->Cell(64,8,$bill['payment_date'] ? 'Paid: '.shmsBillDate($bill['payment_date']) : '',0,1,'R');
        $pdf->SetXY($x+8,188); $pdf->SetFont('DejaVu','',7); $pdf->SetTextColor(108,117,125);
        $pdf->Cell($inner-6,5,'Computer-generated bill. Contact hostel administration for correction.',0,1,'C');
        $pdf->SetDrawColor(120,120,120); $pdf->Line($x+12,196,$x+55,196); $pdf->Line($x+$w-55,196,$x+$w-12,196);
        $pdf->SetTextColor(33,37,41); $pdf->SetFont('DejaVu','',7);
        $pdf->SetXY($x+12,196); $pdf->Cell(43,5,'Resident Signature',0,0,'C');
        $pdf->SetXY($x+$w-55,196); $pdf->Cell(43,5,'Hostel Signature',0,0,'C');
    };

    $drawCopy(7, 'Hostel Copy');
    $pdf->SetDrawColor(160,160,160); $pdf->Line(148.5,7,148.5,202);
    $drawCopy(151, 'Resident Copy');
    $pdf->Output('D','SHMS-Bill-'.str_pad((string)(int)$bill['id'],5,'0',STR_PAD_LEFT).'.pdf');
    exit;
}
