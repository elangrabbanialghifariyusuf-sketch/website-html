<?php
require_once __DIR__ . "/includes/functions.php";
if (($_SESSION["role"] ?? "") === "kasir") {
    header("Location: kasir/dashboard.php");
    exit;
}
$pageTitle = "Menu";
require __DIR__ . "/includes/header.php";
?>
<section>
    <p class="eyebrow">Dibuat saat kamu pesan</p>
    <h1>Menu kafe</h1>
    <p id="menu-error" class="notice error" role="status" hidden></p>
    <div id="menu-list" class="menu-list" aria-live="polite"></div>
    <p class="menu-checkout"><a class="button-link" href="checkout.php">Lanjut ke checkout</a></p>
</section>
<script src="assets/menu.js" defer></script>
<?php require __DIR__ . "/includes/footer.php"; ?>
