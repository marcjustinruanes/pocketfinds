<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'user' => auth()->user(),
    'size' => 40,
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'user' => auth()->user(),
    'size' => 40,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $name = trim(($user->given_names ?? '').' '.($user->last_name ?? ''));
    $baseStyle = "width:{$size}px;height:{$size}px;border-radius:50%;display:grid;place-items:center;object-fit:cover;flex:none;overflow:hidden";
    $customStyle = $attributes->get('style');
?>

<?php if($user && $user->profile_picture): ?>
    <img
        <?php echo e($attributes->except('style')->merge(['class' => 'user-avatar'])); ?>

        src="<?php echo e(asset('storage/'.$user->profile_picture)); ?>"
        alt="<?php echo e($name ?: 'User profile picture'); ?>"
        style="<?php echo e($baseStyle); ?>;<?php echo e($customStyle); ?>"
    >
<?php else: ?>
    <img
        <?php echo e($attributes->except('style')->merge(['class' => 'user-avatar'])); ?>

        src="<?php echo e(asset('images/default-avatar.png')); ?>"
        alt="<?php echo e($name ?: 'Default profile picture'); ?>"
        style="<?php echo e($baseStyle); ?>;<?php echo e($customStyle); ?>"
    >
<?php endif; ?>
<?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\components\user-avatar.blade.php ENDPATH**/ ?>