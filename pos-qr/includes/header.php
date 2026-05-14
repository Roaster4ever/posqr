<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'POS') ?> — <?= SHOP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/style.css">
<link rel="icon" type="image/x-icon" href="favicon.ico?v=2">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="sidebar-brand">
      <span class="brand-icon">◈</span>
      <span class="brand-name"><?= SHOP_NAME ?></span>
    </div>
    <nav class="sidebar-nav">
      <a href="/index.php" class="nav-item <?= ($activePage??'')==='dashboard'?'active':'' ?>">
        <span class="nav-icon">▦</span><span>Dashboard</span>
      </a>
      <a href="/pages/pos.php" class="nav-item <?= ($activePage??'')==='pos'?'active':'' ?>">
        <span class="nav-icon">⊞</span><span>Point of Sale</span>
      </a>
      <a href="/pages/sales.php" class="nav-item <?= ($activePage??'')==='sales'?'active':'' ?>">
        <span class="nav-icon">≡</span><span>Sales</span>
      </a>
      <a href="/pages/products.php" class="nav-item <?= ($activePage??'')==='products'?'active':'' ?>">
        <span class="nav-icon">▤</span><span>Products</span>
      </a>
      <a href="/pages/categories.php" class="nav-item <?= ($activePage??'')==='categories'?'active':'' ?>">
        <span class="nav-icon">◫</span><span>Categories</span>
      </a>
      <a href="/pages/customers.php" class="nav-item <?= ($activePage??'')==='customers'?'active':'' ?>">
        <span class="nav-icon">◎</span><span>Customers</span>
      </a>
      <a href="/pages/expenses.php" class="nav-item <?= ($activePage??'')==='expenses'?'active':'' ?>">
        <span class="nav-icon">◻</span><span>Expenses</span>
      </a>
      <a href="/pages/inventory.php" class="nav-item <?= ($activePage??'')==='inventory'?'active':'' ?>">
        <span class="nav-icon">▥</span><span>Inventory</span>
      </a>
      <a href="/pages/suppliers.php" class="nav-item <?= ($activePage??'')==='suppliers'?'active':'' ?>">
        <span class="nav-icon">◈</span><span>Suppliers</span>
      </a>
      <a href="/pages/orders.php" class="nav-item <?= ($activePage??'')==='orders'?'active':'' ?>">
        <span class="nav-icon">◫</span><span>Orders</span>
      </a>
      <?php if(isAdmin()): ?>
      <a href="/pages/reports.php" class="nav-item <?= ($activePage??'')==='reports'?'active':'' ?>">
        <span class="nav-icon">◈</span><span>Reports</span>
      </a>
      <a href="/pages/users.php" class="nav-item <?= ($activePage??'')==='users'?'active':'' ?>">
        <span class="nav-icon">◉</span><span>Users</span>
      </a>
      <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
      <div class="user-chip">
        <span class="user-avatar"><?= strtoupper(substr($_SESSION['user_name']??'U',0,1)) ?></span>
        <div>
          <div class="user-name"><?= e($_SESSION['user_name']??'') ?></div>
          <div class="user-role"><?= e($_SESSION['user_role']??'') ?></div>
        </div>
      </div>
      <a href="/logout.php" class="btn-logout">Logout</a>
    </div>
  </aside>
  <main class="main-content">
    <?php if(isset($_GET['msg'])): ?>
      <div class="alert alert-success"><?= e($_GET['msg']) ?></div>
    <?php endif; ?>
    <?php if(isset($_GET['error'])): ?>
      <div class="alert alert-danger"><?= e($_GET['error']) ?></div>
    <?php endif; ?>
