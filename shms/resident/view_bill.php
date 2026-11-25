<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_RESIDENT);
require_once __DIR__ . '/../includes/backend.php';
require_once __DIR__ . '/../includes/bill_document.php';
shmsBackendStart(ROLE_RESIDENT);
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT); $bill=$id?shmsLoadBill($id,(int)$_SESSION['user_id']):null;
if(!$bill){setFlash('error','Bill not found.');header('Location: my_bills.php');exit;}
if(($_GET['download']??'')==='pdf'){shmsBillPdf($bill);}
$pageTitle='View Bill';$role=ROLE_RESIDENT;$billBackUrl='my_bills.php';
include __DIR__.'/../includes/header.php'; include __DIR__.'/../includes/sidebar_resident.php';
?>
<div class="content-wrapper"><div class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><i class="fas fa-file-invoice mr-2"></i>Bill Details</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li><li class="breadcrumb-item"><a href="my_bills.php">My Bills</a></li><li class="breadcrumb-item active">View Bill</li></ol></div></div></div></div><section class="content"><div class="container-fluid"><?php showFlash();include __DIR__.'/../includes/bill_view.php';?></div></section></div>
<?php include __DIR__.'/../includes/footer.php'; ?>
