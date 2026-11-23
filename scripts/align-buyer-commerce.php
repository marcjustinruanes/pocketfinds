<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
putenv('POCKETFINDS_BUYER_RUNTIME=1');
require dirname(__DIR__).'/api/bootstrap.php';
require dirname(__DIR__).'/api/BuyerCommerceAlignment.php';
$apply=in_array('--apply',$argv,true);
echo json_encode(['mode'=>$apply?'apply':'dry-run','result'=>BuyerCommerceAlignment::run($apply)],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
