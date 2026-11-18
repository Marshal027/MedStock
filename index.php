<?php
include 'db.php';

$msg = "";
$msg_type = "success";
$active_tab = $_POST['tab'] ?? 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_supplier'])) {
        $stmt = $conn->prepare("INSERT INTO SUPPLIER (supplier_name, phone, address) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $_POST['supplier_name'], $_POST['phone'], $_POST['address']);
        if ($stmt->execute()) $msg = "Supplier added successfully.";
        $stmt->close();
    }

    if (isset($_POST['add_customer'])) {
        $stmt = $conn->prepare("INSERT INTO CUSTOMER (customer_name, phone, address) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $_POST['customer_name'], $_POST['phone'], $_POST['address']);
        if ($stmt->execute()) $msg = "Customer added successfully.";
        $stmt->close();
    }

    if (isset($_POST['add_medicine'])) {
        $stmt = $conn->prepare("INSERT INTO MEDICINE (med_name, category, price, stock, supplier_ID) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdii", $_POST['med_name'], $_POST['category'], $_POST['price'], $_POST['stock'], $_POST['supplier_ID']);
        if ($stmt->execute()) $msg = "Medicine added to catalog.";
        $stmt->close();
    }

    if (isset($_POST['add_purchase'])) {
        $qty = (int)$_POST['pur_quantity'];
        $med_id = (int)$_POST['med_ID'];
        $stmt =$conn->prepare("INSERT INTO PURCHASE (supplier_ID, med_ID, purchase_date, pur_quantity, unit_cost) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisid", $_POST['supplier_ID'], $med_id,$_POST['purchase_date'], $qty,$_POST['unit_cost']);
        if ($stmt->execute()) {$conn->query("UPDATE MEDICINE SET stock = stock + $qty WHERE med_Id =$med_id");
            $msg = "Purchase logged (+$qty units added to stock).";
        }
        $stmt->close();
    }

    if (isset($_POST['add_sale'])) {
        $qty = (int)$_POST['quantity'];
        $med_id = (int)$_POST['med_ID'];
        $med =$conn->query("SELECT price, stock FROM MEDICINE WHERE med_Id = $med_id")->fetch_assoc();

        if ($med &&$med['stock'] >= $qty) {$total = $med['price'] *$qty;
            $stmt =$conn->prepare("INSERT INTO SALES (customer_ID, med_ID, sales_date, quantity, total_price) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisid", $_POST['customer_ID'], $med_id,$_POST['sales_date'], $qty,$total);
            if ($stmt->execute()) {$conn->query("UPDATE MEDICINE SET stock = stock - $qty WHERE med_Id =$med_id");
                $msg = "Sale recorded! Total: ₹" . number_format($total, 2);
            }$stmt->close();
        } else {
            $msg = "Insufficient stock for this medicine.";
            $msg_type = "error";
        }
    }
}


$sales_sum =$conn->query("SELECT COALESCE(SUM(total_price), 0) AS rev FROM SALES")->fetch_assoc()['rev'];
$med_info  =$conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(stock), 0) AS units FROM MEDICINE")->fetch_assoc();
$low_cnt   =$conn->query("SELECT COUNT(*) AS cnt FROM MEDICINE WHERE stock < 10")->fetch_assoc()['cnt'];
$pur_units =$conn->query("SELECT COALESCE(SUM(pur_quantity), 0) AS qty FROM PURCHASE")->fetch_assoc()['qty'];

// Category Breakdown for Donut Chart
$cat_rows = [];$cat_total = 0;
$cat_res =$conn->query("SELECT category, SUM(stock) AS qty FROM MEDICINE GROUP BY category LIMIT 4");
while ($c =$cat_res->fetch_assoc()) {
    $cat_rows[] =$c;
    $cat_total += (int)$c['qty'];
}

// Latest Sales for Bar Chart
$bar_rows = [];$max_sale = 1;
$bar_res =$conn->query("SELECT s.sales_ID, m.med_name, s.total_price FROM SALES s JOIN MEDICINE m ON s.med_ID = m.med_Id ORDER BY s.sales_ID DESC LIMIT 6");
while ($b =$bar_res->fetch_assoc()) {
    $bar_rows[] =$b;
    if ($b['total_price'] >$max_sale) $max_sale =$b['total_price'];
}
$bar_rows = array_reverse($bar_rows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedStock | Medical Store Management System</title>
    <link rel="icon" type="image/png" href="MedStock_logo.png">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-layout">

   
    <aside class="sidebar">
        <div class="brand">
            <img src="MedStock_logo.png" alt="MedStock Logo" class="brand-logo">
            <h2>MedStock</h2>
        </div>

        <div>
            <div class="nav-group-label">MAIN MENU</div>
            <button class="nav-item <?= $active_tab === 'dashboard' ? 'active' : '' ?>" onclick="showTab(event, 'dashboard')">Dashboard</button>
            <button class="nav-item <?= $active_tab === 'medicines' ? 'active' : '' ?>" onclick="showTab(event, 'medicines')">Medicines</button>
        </div>

        <div>
            <div class="nav-group-label">DIRECTORY</div>
            <button class="nav-item <?= $active_tab === 'suppliers' ? 'active' : '' ?>" onclick="showTab(event, 'suppliers')">Suppliers</button>
            <button class="nav-item <?= $active_tab === 'customers' ? 'active' : '' ?>" onclick="showTab(event, 'customers')">Customers</button>
        </div>

        <div>
            <div class="nav-group-label">TRANSACTIONS</div>
            <button class="nav-item <?= $active_tab === 'purchases' ? 'active' : '' ?>" onclick="showTab(event, 'purchases')">Purchases</button>
            <button class="nav-item <?= $active_tab === 'sales' ? 'active' : '' ?>" onclick="showTab(event, 'sales')">Sales</button>
        </div>
    </aside>

    <main class="main-area">
        <header class="top-header">
            <div>
                <h1>Welcome to MedStock Pharmacy</h1>
                <p style="color: var(--muted); font-size: 13px;">Inventory, Supplier &amp; Customer Billing Overview</p>
            </div>
            <div class="status-pill">● MySQL Connected: medical_store_db</div>
        </header>

        <?php if ($msg): ?>
            <div class="alert <?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

    
        <section id="dashboard" class="section <?= $active_tab === 'dashboard' ? 'active' : '' ?>">
           
            <div class="kpi-row">
                <div class="kpi-box kpi-cream">
                    <div>
                        <span>Total Sales</span>
                        <h2>₹<?= number_format($sales_sum, 2) ?></h2>
                    </div>
                    <div class="mini-bars"><i style="height:22px"></i><i style="height:34px"></i><i class="tall" style="height:44px"></i></div>
                </div>

                <div class="kpi-box kpi-mint">
                    <div>
                        <span>Total Stock Units</span>
                        <h2><?= $med_info['units'] ?></h2>
                    </div>
                    <div class="mini-bars"><i style="height:28px"></i><i class="tall" style="height:42px"></i><i style="height:30px"></i></div>
                </div>

                <div class="kpi-box kpi-verdigris">
                    <div>
                        <span>Low Stock (&lt;10)</span>
                        <h2><?= $low_cnt ?> Items</h2>
                    </div>
                    <div class="mini-bars"><i class="tall" style="height:40px"></i><i style="height:24px"></i><i style="height:18px"></i></div>
                </div>

                <div class="kpi-box kpi-baltic">
                    <div>
                        <span>Purchased Units</span>
                        <h2><?= $pur_units ?></h2>
                    </div>
                    <div class="mini-bars"><i style="height:20px"></i><i style="height:32px"></i><i class="tall" style="height:44px"></i></div>
                </div>
            </div>

            <!-- Middle Analytics Row -->
            <div class="analytics-grid">
                <!-- Stock Distribution Donut -->
                <div class="neu-card">
                    <h3>Stock by Category</h3>
                    <div class="donut-wrap">
                        <?php
                        $colors = ['#05668D', '#028090', '#00A896', '#02C39A'];
                        $offset = 0;
                        ?>
                        <svg width="130" height="130" viewBox="0 0 36 36">
                            <circle cx="18" cy="18" r="14" fill="none" stroke="#dce6e6" stroke-width="5"></circle>
                            <?php foreach ($cat_rows as $i =>$c):
                                $pct =$cat_total > 0 ? round(($c['qty'] /$cat_total) * 100) : 0;
                                $col = $colors[$i % 4];
                            ?>
                                <circle cx="18" cy="18" r="14" fill="none" stroke="<?= $col ?>" stroke-width="5"
                                        stroke-dasharray="<?= $pct ?> <?= 100 - $pct ?>"
                                        stroke-dashoffset="-<?= $offset ?>" transform="rotate(-90 18 18)"></circle>
                            <?php $offset +=$pct; endforeach; ?>
                            <text x="18" y="20" text-anchor="middle" font-size="6" font-weight="700" fill="#05668D"><?= $med_info['cnt'] ?> Meds</text>
                        </svg>

                        <ul class="legend-list">
                            <?php foreach ($cat_rows as $i =>$c): ?>
                                <li>
                                    <span class="dot" style="background:<?= $colors[$i % 4] ?>"></span>
                                    <?= htmlspecialchars($c['category']) ?> (<?=$c['qty'] ?>)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Sales Overview Bars -->
                <div class="neu-card">
                    <h3>Recent Sales Overview</h3>
                    <div class="bar-chart">
                        <?php foreach ($bar_rows as $b):$h = max(15, round(($b['total_price'] /$max_sale) * 100));
                        ?>
                            <div class="bar-col" title="₹<?= $b['total_price'] ?>">
                                <div class="bar-track">
                                    <div class="bar-fill" style="height: <?= $h ?>%;"></div>
                                </div>
                                <small>#<?= $b['sales_ID'] ?> (₹<?= (int)$b['total_price'] ?>)</small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Sales List -->
            <div class="neu-card">
                <div class="table-header">
                    <h3 style="margin:0;">Recent Sales List</h3>
                    <input type="text" class="search-input" placeholder="Search sales..." onkeyup="filterTable(this, 'recentSalesTable')">
                </div>
                <div class="table-wrap">
                    <table id="recentSalesTable">
                        <thead>
                            <tr><th>Sale ID</th><th>Customer</th><th>Medicine</th><th>Date</th><th>Qty</th><th>Total Price</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $recent =$conn->query("SELECT s.sales_ID, c.customer_name, m.med_name, s.sales_date, s.quantity, s.total_price 
                                                    FROM SALES s 
                                                    JOIN CUSTOMER c ON s.customer_ID = c.customer_Id 
                                                    JOIN MEDICINE m ON s.med_ID = m.med_Id 
                                                    ORDER BY s.sales_ID DESC");
                            while ($r =$recent->fetch_assoc()) {
                                echo "<tr>
                                    <td>#{$r['sales_ID']}</td>
                                    <td><strong>" . htmlspecialchars($r['customer_name']) . "</strong></td>
                                    <td>" . htmlspecialchars($r['med_name']) . "</td>
                                    <td>{$r['sales_date']}</td>
                                    <td>{$r['quantity']}</td>
                                    <td><strong>₹" . number_format($r['total_price'], 2) . "</strong></td>
                                </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- 2. MEDICINES -->
        <section id="medicines" class="section <?= $active_tab === 'medicines' ? 'active' : '' ?>">
            <div class="split-grid">
                <div class="neu-card">
                    <h3>Add Medicine</h3>
                    <form method="POST">
                        <input type="hidden" name="tab" value="medicines">
                        <label>Medicine Name</label>
                        <input type="text" name="med_name" required>
                        <label>Category</label>
                        <input type="text" name="category" required>
                        <label>Price (₹)</label>
                        <input type="number" step="0.01" name="price" required>
                        <label>Initial Stock</label>
                        <input type="number" name="stock" required>
                        <label>Supplier</label>
                        <select name="supplier_ID" required>
                            <?php
                            $sup =$conn->query("SELECT supplier_ID, supplier_name FROM SUPPLIER");
                            while ($s =$sup->fetch_assoc()) echo "<option value='{$s['supplier_ID']}'>#{$s['supplier_ID']} - " . htmlspecialchars($s['supplier_name']) . "</option>";
                            ?>
                        </select>
                        <button type="submit" name="add_medicine" class="btn-primary">Save Medicine</button>
                    </form>
                </div>

                <div class="neu-card">
                    <h3>Medicine Inventory</h3>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>med_Id</th><th>med_name</th><th>category</th><th>price</th><th>stock</th><th>supplier_ID</th></tr></thead>
                            <tbody>
                                <?php
                                $res =$conn->query("SELECT * FROM MEDICINE");
                                while ($r =$res->fetch_assoc()) {
                                    $cls =$r['stock'] < 10 ? 'badge-low' : 'badge-ok';
                                    echo "<tr><td>{$r['med_Id']}</td><td><strong>" . htmlspecialchars($r['med_name']) . "</strong></td><td>" . htmlspecialchars($r['category']) . "</td><td>₹{$r['price']}</td><td><span class='badge $cls'>{$r['stock']}</span></td><td>{$r['supplier_ID']}</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. SUPPLIERS -->
        <section id="suppliers" class="section <?= $active_tab === 'suppliers' ? 'active' : '' ?>">
            <div class="split-grid">
                <div class="neu-card">
                    <h3>Add Supplier</h3>
                    <form method="POST">
                        <input type="hidden" name="tab" value="suppliers">
                        <label>Supplier Name</label>
                        <input type="text" name="supplier_name" required>
                        <label>Phone</label>
                        <input type="text" name="phone" required>
                        <label>Address</label>
                        <input type="text" name="address" required>
                        <button type="submit" name="add_supplier" class="btn-primary">Save Supplier</button>
                    </form>
                </div>
                <div class="neu-card">
                    <h3>Suppliers Directory</h3>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>supplier_ID</th><th>supplier_name</th><th>phone</th><th>address</th></tr></thead>
                            <tbody>
                                <?php
                                $res =$conn->query("SELECT * FROM SUPPLIER");
                                while ($r =$res->fetch_assoc()) {
                                    echo "<tr><td>{$r['supplier_ID']}</td><td><strong>" . htmlspecialchars($r['supplier_name']) . "</strong></td><td>" . htmlspecialchars($r['phone']) . "</td><td>" . htmlspecialchars($r['address']) . "</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- 4. CUSTOMERS -->
        <section id="customers" class="section <?= $active_tab === 'customers' ? 'active' : '' ?>">
            <div class="split-grid">
                <div class="neu-card">
                    <h3>Add Customer</h3>
                    <form method="POST">
                        <input type="hidden" name="tab" value="customers">
                        <label>Customer Name</label>
                        <input type="text" name="customer_name" required>
                        <label>Phone</label>
                        <input type="text" name="phone" required>
                        <label>Address</label>
                        <input type="text" name="address" required>
                        <button type="submit" name="add_customer" class="btn-primary">Save Customer</button>
                    </form>
                </div>
                <div class="neu-card">
                    <h3>Customers Directory</h3>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>customer_Id</th><th>customer_name</th><th>phone</th><th>address</th></tr></thead>
                            <tbody>
                                <?php
                                $res =$conn->query("SELECT * FROM CUSTOMER");
                                while ($r =$res->fetch_assoc()) {
                                    echo "<tr><td>{$r['customer_Id']}</td><td><strong>" . htmlspecialchars($r['customer_name']) . "</strong></td><td>" . htmlspecialchars($r['phone']) . "</td><td>" . htmlspecialchars($r['address']) . "</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- 5. PURCHASES -->
        <section id="purchases" class="section <?= $active_tab === 'purchases' ? 'active' : '' ?>">
            <div class="split-grid">
                <div class="neu-card">
                    <h3>Log Purchase</h3>
                    <form method="POST">
                        <input type="hidden" name="tab" value="purchases">
                        <label>Supplier</label>
                        <select name="supplier_ID" required>
                            <?php
                            $sup =$conn->query("SELECT supplier_ID, supplier_name FROM SUPPLIER");
                            while ($s =$sup->fetch_assoc()) echo "<option value='{$s['supplier_ID']}'>#{$s['supplier_ID']} - " . htmlspecialchars($s['supplier_name']) . "</option>";
                            ?>
                        </select>
                        <label>Medicine</label>
                        <select name="med_ID" required>
                            <?php
                            $meds =$conn->query("SELECT med_Id, med_name FROM MEDICINE");
                            while ($m =$meds->fetch_assoc()) echo "<option value='{$m['med_Id']}'>#{$m['med_Id']} - " . htmlspecialchars($m['med_name']) . "</option>";
                            ?>
                        </select>
                        <label>Purchase Date</label>
                        <input type="date" name="purchase_date" value="<?= date('Y-m-d') ?>" required>
                        <label>Quantity</label>
                        <input type="number" name="pur_quantity" min="1" required>
                        <label>Unit Cost (₹)</label>
                        <input type="number" step="0.01" name="unit_cost" min="0" required>
                        <button type="submit" name="add_purchase" class="btn-primary">Log Purchase</button>
                    </form>
                </div>
                <div class="neu-card">
                    <h3>Purchase Records</h3>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>purchase_ID</th><th>Supplier</th><th>Medicine</th><th>Date</th><th>Qty</th><th>Unit Cost</th><th>Total</th></tr></thead>
                            <tbody>
                                <?php
                                $res =$conn->query("SELECT p.*, s.supplier_name, m.med_name FROM PURCHASE p JOIN SUPPLIER s ON p.supplier_ID = s.supplier_ID JOIN MEDICINE m ON p.med_ID = m.med_Id ORDER BY p.purchase_ID DESC");
                                while ($r =$res->fetch_assoc()) {
                                    $tot =$r['pur_quantity'] * $r['unit_cost'];
                                    echo "<tr><td>#{$r['purchase_ID']}</td><td>" . htmlspecialchars($r['supplier_name']) . "</td><td><strong>" . htmlspecialchars($r['med_name']) . "</strong></td><td>{$r['purchase_date']}</td><td>{$r['pur_quantity']}</td><td>₹{$r['unit_cost']}</td><td><strong>₹" . number_format($tot, 2) . "</strong></td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- 6. SALES -->
        <section id="sales" class="section <?= $active_tab === 'sales' ? 'active' : '' ?>">
            <div class="split-grid">
                <div class="neu-card">
                    <h3>Record Sale</h3>
                    <form method="POST">
                        <input type="hidden" name="tab" value="sales">
                        <label>Customer</label>
                        <select name="customer_ID" required>
                            <?php
                            $cust =$conn->query("SELECT customer_Id, customer_name FROM CUSTOMER");
                            while ($c =$cust->fetch_assoc()) echo "<option value='{$c['customer_Id']}'>#{$c['customer_Id']} - " . htmlspecialchars($c['customer_name']) . "</option>";
                            ?>
                        </select>
                        <label>Medicine</label>
                        <select name="med_ID" required>
                            <?php
                            $meds =$conn->query("SELECT med_Id, med_name, price, stock FROM MEDICINE");
                            while ($m =$meds->fetch_assoc()) echo "<option value='{$m['med_Id']}'>#{$m['med_Id']} - " . htmlspecialchars($m['med_name']) . " (Stock: {$m['stock']}, ₹{$m['price']})</option>";
                            ?>
                        </select>
                        <label>Sale Date</label>
                        <input type="date" name="sales_date" value="<?= date('Y-m-d') ?>" required>
                        <label>Quantity</label>
                        <input type="number" name="quantity" min="1" required>
                        <button type="submit" name="add_sale" class="btn-primary">Complete Sale</button>
                    </form>
                </div>
                <div class="neu-card">
                    <h3>All Sales Records</h3>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>sales_ID</th><th>Customer</th><th>Medicine</th><th>Date</th><th>Qty</th><th>Total Price</th></tr></thead>
                            <tbody>
                                <?php
                                $res =$conn->query("SELECT s.*, c.customer_name, m.med_name FROM SALES s JOIN CUSTOMER c ON s.customer_ID = c.customer_Id JOIN MEDICINE m ON s.med_ID = m.med_Id ORDER BY s.sales_ID DESC");
                                while ($r =$res->fetch_assoc()) {
                                    echo "<tr><td>#{$r['sales_ID']}</td><td><strong>" . htmlspecialchars($r['customer_name']) . "</strong></td><td>" . htmlspecialchars($r['med_name']) . "</td><td>{$r['sales_date']}</td><td>{$r['quantity']}</td><td><strong>₹" . number_format($r['total_price'], 2) . "</strong></td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<script>
function showTab(evt, tabId) {
    document.querySelectorAll('.section').forEach(sec => sec.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(btn => btn.classList.remove('active'));
    const targetSection = document.getElementById(tabId);
    if (targetSection) targetSection.classList.add('active');
    if (evt && evt.currentTarget) {
        evt.currentTarget.classList.add('active');
    } else {
        const matchingBtn = document.querySelector(`.nav-item[onclick*="'${tabId}'"]`);
        if (matchingBtn) matchingBtn.classList.add('active');
    }
}

function filterTable(input, tableId) {
    const filter = input.value.toLowerCase();
    const table = document.getElementById(tableId);
    if (!table) return;
    const tbody = table.getElementsByTagName('tbody')[0];
    if (!tbody) return;
    const rows = tbody.getElementsByTagName('tr');
    for (let i = 0; i < rows.length; i++) {
        const text = rows[i].textContent.toLowerCase();
        rows[i].style.display = text.includes(filter) ? '' : 'none';
    }
}
</script>
</body>
</html>