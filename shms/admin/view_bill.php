<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_ADMIN);
require_once __DIR__ . '/../includes/backend.php';
require_once __DIR__ . '/../includes/bill_document.php';
shmsBackendStart(ROLE_ADMIN);
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT); $bill=$id?shmsLoadBill($id):null;
if(!$bill){setFlash('error','Bill not found.');header('Location: bills.php');exit;}
if(($_GET['download']??'')==='pdf'){shmsBillPdf($bill);}
$pageTitle='View Bill';$role=ROLE_ADMIN;$billBackUrl='bills.php';
include __DIR__.'/../includes/header.php'; include __DIR__.'/../includes/sidebar_admin.php';
?>
<div class="content-wrapper"><div class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><i class="fas fa-file-invoice mr-2"></i>Bill Details</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li><li class="breadcrumb-item"><a href="bills.php">Bills</a></li><li class="breadcrumb-item active">View Bill</li></ol></div></div></div></div><section class="content"><div class="container-fluid"><?php showFlash();include __DIR__.'/../includes/bill_view.php';?></div></section></div>
<?php include __DIR__.'/../includes/footer.php'; ?>
