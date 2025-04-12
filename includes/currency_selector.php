<?php
$currencies = get_currencies();
$active_currency = get_active_currency();
?>

<div class="dropdown">
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="currencyDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <?php echo htmlspecialchars($active_currency['code']); ?>
    </button>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="currencyDropdown">
        <?php foreach ($currencies as $currency): ?>
        <li>
            <a class="dropdown-item <?php echo $active_currency['code'] === $currency['code'] ? 'active' : ''; ?>" 
               href="update_currency.php?code=<?php echo htmlspecialchars($currency['code']); ?>&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">
                <?php echo htmlspecialchars($currency['code']); ?> - <?php echo htmlspecialchars($currency['name']); ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</div> 