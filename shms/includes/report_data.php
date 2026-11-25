<?php
require_once __DIR__ . '/enhancements.php';

function shmsReport($type, $range) {
    $params = [$range['start'], $range['end']];
    if ($type === 'billing') {
        $rows = shmsRows("SELECT b.id,r.name,b.month,b.year,b.generated_at,b.due_date,b.payment_status,b.total_amount,
            CASE WHEN b.payment_status='unpaid' AND b.due_date<CURDATE() THEN 'Yes' ELSE 'No' END AS overdue
            FROM bills b JOIN residents r ON r.id=b.resident_id
            WHERE b.generated_at>=? AND b.generated_at<? ORDER BY b.generated_at DESC,b.id DESC", $params);
        $summary = shmsRows("SELECT COUNT(*) AS generated_count,
            COALESCE(SUM(payment_status='paid'),0) AS paid, COALESCE(SUM(payment_status='unpaid'),0) AS unpaid,
            COALESCE(SUM(payment_status='unpaid' AND due_date<CURDATE()),0) AS overdue,
            COALESCE(SUM(total_amount),0) AS billed,
            COALESCE(SUM(CASE WHEN payment_status='unpaid' THEN total_amount ELSE 0 END),0) AS outstanding
            FROM bills WHERE generated_at>=? AND generated_at<?", $params)[0];
        $data=[];
        foreach ($rows as $r) {
            $data[] = [$r['id'],$r['name'],sprintf('%04d-%02d',$r['year'],$r['month']),$r['generated_at'],
                $r['due_date'] ?: 'Not set',ucfirst($r['payment_status']),$r['overdue'],$r['total_amount']];
        }
        return ['title'=>'Billing Report','headers'=>['Bill ID','Resident','Billing month','Generated','Due date','Status','Overdue now','Amount (Rs.)'],
            'rows'=>$data,'summary'=>['Bills generated'=>$summary['generated_count'],'Paid bills'=>$summary['paid'],'Unpaid bills'=>$summary['unpaid'],
                'Overdue bills now'=>$summary['overdue'],'Total billed (Rs.)'=>number_format((float)$summary['billed'],2),
                'Outstanding (Rs.)'=>number_format((float)$summary['outstanding'],2)],
            'note'=>'Period uses the bill generation date, not the billing month. Payment and overdue status are current, not historical. A bill is overdue only when unpaid and its assigned due date is before today. No due date means no overdue classification.',
            'raw'=>$rows];
    }
    if ($type === 'attendance') {
        $meals = ['breakfast','lunch','snacks','dinner'];
        $availability = shmsRows('SELECT MAX(breakfast_enabled) breakfast,MAX(lunch_enabled) lunch,MAX(snacks_enabled) snacks,MAX(dinner_enabled) dinner FROM mess_daily_menu WHERE menu_date>=? AND menu_date<?', $params)[0] ?? [];
        $visibleMeals = array_values(array_filter($meals, static function ($meal) use ($availability) { return !empty($availability[$meal]); }));
        $rows = shmsRows('SELECT a.date,r.name,a.breakfast,a.lunch,a.snacks,a.dinner,
            COALESCE(m.breakfast_enabled,0) breakfast_enabled,COALESCE(m.lunch_enabled,0) lunch_enabled,
            COALESCE(m.snacks_enabled,0) snacks_enabled,COALESCE(m.dinner_enabled,0) dinner_enabled
            FROM mess_attendance a JOIN residents r ON r.id=a.resident_id LEFT JOIN mess_daily_menu m ON m.menu_date=a.date
            WHERE a.date>=? AND a.date<? ORDER BY a.date,r.name,a.id', $params);
        $summary = shmsRows('SELECT COUNT(*) AS records,COUNT(DISTINCT resident_id) AS residents,
            COALESCE(SUM(breakfast),0) AS breakfast,COALESCE(SUM(lunch),0) AS lunch,COALESCE(SUM(snacks),0) AS snacks,COALESCE(SUM(dinner),0) AS dinner
            FROM mess_attendance WHERE date>=? AND date<?', $params)[0];
        $data=[];
        foreach ($rows as $r) {
            $line = [$r['date'],$r['name']];
            foreach ($visibleMeals as $meal) { $line[] = empty($r[$meal . '_enabled']) ? 'Not offered' : ($r[$meal] ? 'Yes' : 'No'); }
            $data[] = $line;
        }
        $headers = ['Date','Resident'];
        $reportSummary = ['Resident-day records'=>$summary['records'],'Residents recorded'=>$summary['residents']];
        foreach ($visibleMeals as $meal) { $headers[] = ucfirst($meal); $reportSummary[ucfirst($meal) . 's'] = $summary[$meal]; }
        return ['title'=>'Resident Mess Attendance Report','headers'=>$headers,'rows'=>$data,
            'summary'=>$reportSummary,
            'note'=>'Only meals enabled at least once in the selected period are shown. “Not offered” means that meal was disabled on that date. Missing resident/date records are unrecorded; they are not counted as absence.'];
    }
    if ($type === 'expenses') {
        $rows = shmsRows('SELECT expense_date,title,category,description,amount,remarks FROM hostel_expenses WHERE expense_date>=? AND expense_date<? ORDER BY expense_date,id', $params);
        $total = shmsValue('SELECT COALESCE(SUM(amount),0) FROM hostel_expenses WHERE expense_date>=? AND expense_date<?', $params);
        $categories = shmsRows('SELECT category,COUNT(*) AS records,SUM(amount) AS amount FROM hostel_expenses WHERE expense_date>=? AND expense_date<? GROUP BY category ORDER BY category', $params);
        return ['title'=>'Hostel Expense Report','headers'=>['Date','Title','Category','Description','Amount (Rs.)','Remarks'],'rows'=>array_map('array_values',$rows),
            'summary'=>['Expense records'=>count($rows),'Total expenses (Rs.)'=>number_format((float)$total,2)],
            'note'=>'Period uses the expense date. Hostel operating costs are separate from miscellaneous charges billed to residents.', 'categories'=>$categories];
    }
    if ($type === 'occupancy') {
        // Allocation overlap: alloc_date inclusive, vacate_date exclusive. Never infer missing historical vacate dates.
        $rows = shmsRows("SELECT rm.room_number,rt.beds,rm.status,
            (SELECT COUNT(*) FROM room_allocations a WHERE a.room_id=rm.id AND a.is_active=1) AS current_beds,
            (SELECT COUNT(*) FROM room_allocations a WHERE a.room_id=rm.id AND a.alloc_date<? AND
              (a.vacate_date>? OR (a.vacate_date IS NULL AND a.is_active=1))) AS period_allocations
            FROM rooms rm JOIN room_types rt ON rt.id=rm.room_type_id ORDER BY rm.room_number", [$range['end'],$range['start']]);
        $occupied=0; $available=0; $periodOccupied=0; $maintenance=0; $data=[];
        foreach ($rows as $r) {
            if ((int)$r['current_beds']>0) { $occupied++; }
            if ($r['status']==='maintenance') { $maintenance++; }
            if ($r['status']!=='maintenance' && (int)$r['current_beds']<(int)$r['beds']) { $available++; }
            if ((int)$r['period_allocations']>0) { $periodOccupied++; }
            $data[]=[$r['room_number'],$r['beds'],$r['status'],$r['current_beds'],
                $r['status']==='maintenance'?0:max(0,(int)$r['beds']-(int)$r['current_beds']),
                (int)$r['period_allocations']>0?'Yes':'No',$r['period_allocations']];
        }
        $allocations=shmsRows('SELECT rm.room_number,r.name,a.alloc_date,a.vacate_date FROM room_allocations a JOIN rooms rm ON rm.id=a.room_id JOIN residents r ON r.id=a.resident_id WHERE a.alloc_date<? AND (a.vacate_date>? OR (a.vacate_date IS NULL AND a.is_active=1)) ORDER BY rm.room_number,a.alloc_date,a.id',[$range['end'],$range['start']]);
        $unknown=shmsValue('SELECT COUNT(*) FROM room_allocations WHERE is_active=0 AND vacate_date IS NULL AND alloc_date<?',[$range['end']]);
        return ['title'=>'Room Occupancy Report','headers'=>['Room','Capacity','Room status now','Allocated beds now','Free beds now','Occupied during period','Overlapping allocations'],
            'rows'=>$data,'summary'=>['Total rooms now'=>count($rows),'Occupied rooms now'=>$occupied,'Rooms with free beds now'=>$available,
                'Maintenance rooms now'=>$maintenance,'Rooms occupied during period'=>$periodOccupied],
            'note'=>'Current room capacity, status and free beds are a snapshot today. Selected-period occupancy means at least one dated allocation overlapped the period; it is not a historical vacancy rate. Allocation date is inclusive and vacate date exclusive. Overlapping allocation count is not simultaneous bed usage. ' . $unknown . ' inactive allocations lack a vacate date and are excluded from period totals.',
            'allocations'=>$allocations];
    }
    throw new InvalidArgumentException('Choose a valid report.');
}
