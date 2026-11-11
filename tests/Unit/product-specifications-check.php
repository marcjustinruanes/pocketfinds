<?php
require __DIR__.'/../../app/Support/ProductSpecifications.php';

use App\Support\ProductSpecifications;

$fields = ['weight_grams' => 90, 'weight_grams_max' => 120, 'length_cm' => 20, 'width_cm' => 12.5, 'height_cm' => 16, 'condition' => 'new'];
$rows = ProductSpecifications::build($fields, [['label'=>'Weight','value'=>'old'], ['label'=>'Texture','value'=>'Smooth']]);
if ($rows !== [
    ['label'=>'Weight','value'=>'90 - 120 g'],
    ['label'=>'Dimensions','value'=>'20 × 12.5 × 16 cm (L × W × H)'],
    ['label'=>'Condition','value'=>'New'],
    ['label'=>'Texture','value'=>'Smooth'],
]) throw new RuntimeException('Automatic specifications or custom details were incorrect.');
if (ProductSpecifications::build(['length_cm'=>20, 'width_cm'=>12]) !== []) throw new RuntimeException('Incomplete dimensions must not be generated.');
$rows = ProductSpecifications::build(['weight_grams'=>200, 'condition'=>'used'], $rows);
if (count($rows) !== 3 || $rows[0]['value'] !== '200 g' || $rows[1]['value'] !== 'Used' || $rows[2]['label'] !== 'Texture') throw new RuntimeException('Editing must replace automatic values and retain custom details.');
$twoDimensions = ProductSpecifications::build(['width_cm'=>20, 'height_cm'=>16]);
if (count($twoDimensions)!==1 || $twoDimensions[0]['value']!=="20 \u{00D7} 16 cm (W \u{00D7} H)") throw new RuntimeException('Width and height must work without length.');
echo "Product specification checks passed.\n";
