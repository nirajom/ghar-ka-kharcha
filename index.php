<?php
session_start();

// DEBUG (optional):
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

$ADMIN_PASSWORD = 'Om@123';

$isAdmin = !empty($_SESSION['is_admin']);

// DB CONNECT
$db = new PDO('sqlite:expenses.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// CREATE TABLES (if not exists)
$db->exec("
CREATE TABLE IF NOT EXISTS members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE,
    is_active INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS contributions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    member_id INTEGER,
    amount REAL,
    date TEXT,
    note TEXT
);

CREATE TABLE IF NOT EXISTS expenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    amount REAL,
    date TEXT,
    note TEXT,
    paid_by INTEGER
);

CREATE TABLE IF NOT EXISTS expense_participants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    expense_id INTEGER,
    member_id INTEGER,
    share REAL
);

CREATE TABLE IF NOT EXISTS electricity_bills (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    month_label TEXT,
    start_date TEXT,
    end_date TEXT,
    direct_prev REAL,
    direct_curr REAL,
    inverter_prev REAL,
    inverter_curr REAL,
    direct_units REAL,
    inverter_units REAL,
    unit_rate REAL,
    total_amount REAL,
    note TEXT,
    attachment_direct TEXT,
    attachment_inverter TEXT,
    direct_prev_photo TEXT,
    direct_curr_photo TEXT,
    inverter_prev_photo TEXT,
    inverter_curr_photo TEXT
);
");

// SAFE ALTER (old data ko touch nahi)
try { $db->exec("ALTER TABLE contributions ADD COLUMN attachment TEXT"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE expenses ADD COLUMN attachment TEXT"); } catch (Exception $e) {}

try { $db->exec("ALTER TABLE electricity_bills ADD COLUMN month_label TEXT"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE electricity_bills ADD COLUMN start_date TEXT"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE electricity_bills ADD COLUMN end_date TEXT"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE electricity_bills ADD COLUMN direct_prev REAL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE electricity_bills ADD COLUMN direct_curr REAL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE electricity_bills ADD COLUMN inverter_prev REAL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE electricity_bills ADD COLUMN inverter_curr REAL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE electricity_bills ADD COLUMN direct_units REAL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE electricity_bills ADD COLUMN inverter_units REAL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE electricity_bills ADD COLUMN unit_rate REAL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE electricity_bills ADD COLUMN total_amount REAL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE electricity_bills ADD COLUMN note TEXT"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE electricity_bills ADD COLUMN attachment_direct TEXT"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE electricity_bills ADD COLUMN attachment_inverter TEXT"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE electricity_bills ADD COLUMN direct_prev_photo TEXT"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE electricity_bills ADD COLUMN direct_curr_photo TEXT"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE electricity_bills ADD COLUMN inverter_prev_photo TEXT"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE electricity_bills ADD COLUMN inverter_curr_photo TEXT"); } catch (Exception $e) {}

// Default members
$defaultMembers = ["Om", "Vaibhav", "Sourav", "Uttam", "Siddharth", "Rishab"];
$check = $db->query("SELECT COUNT(*) AS c FROM members")->fetch(PDO::FETCH_ASSOC);
if ($check['c'] == 0) {
    $stmt = $db->prepare("INSERT INTO members (name) VALUES (?)");
    foreach ($defaultMembers as $name) {
        $stmt->execute([$name]);
    }
}

// Members list
$membersStmt = $db->query("SELECT id, name FROM members WHERE is_active = 1 ORDER BY name");
$members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

$memberNames = [];
foreach ($members as $m) {
    $memberNames[$m['id']] = $m['name'];
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function save_upload($field, $prefix) {
    if (empty($_FILES[$field]['name'])) return null;
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;

    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }
    $ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
    if ($ext === '') $ext = 'jpg';
    $filename = $prefix . '_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;

    if (move_uploaded_file($_FILES[$field]['tmp_name'], $uploadDir . $filename)) {
        return 'uploads/' . $filename;
    }
    return null;
}

// LOGIN / LOGOUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $pass = $_POST['password'] ?? '';
    if ($pass === $ADMIN_PASSWORD) {
        $_SESSION['is_admin'] = true;
        header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    } else {
        $loginError = "Wrong password!";
    }
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$isAdmin = !empty($_SESSION['is_admin']);

// DELETE actions
if ($isAdmin && isset($_GET['delete_contribution'])) {
    $id = (int)$_GET['delete_contribution'];
    if ($id > 0) {
        $stmt = $db->prepare("SELECT attachment FROM contributions WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['attachment'])) {
            $path = __DIR__ . '/' . $row['attachment'];
            if (is_file($path)) @unlink($path);
        }
        $stmt = $db->prepare("DELETE FROM contributions WHERE id = ?");
        $stmt->execute([$id]);
    }
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if ($isAdmin && isset($_GET['delete_expense'])) {
    $id = (int)$_GET['delete_expense'];
    if ($id > 0) {
        $stmt = $db->prepare("SELECT attachment FROM expenses WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['attachment'])) {
            $path = __DIR__ . '/' . $row['attachment'];
            if (is_file($path)) @unlink($path);
        }
        $stmt = $db->prepare("DELETE FROM expense_participants WHERE expense_id = ?");
        $stmt->execute([$id]);
        $stmt = $db->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->execute([$id]);
    }
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if ($isAdmin && isset($_GET['delete_bill'])) {
    $id = (int)$_GET['delete_bill'];
    if ($id > 0) {
        $stmt = $db->prepare("SELECT attachment_direct, attachment_inverter, direct_prev_photo, direct_curr_photo, inverter_prev_photo, inverter_curr_photo FROM electricity_bills WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            foreach (['attachment_direct','attachment_inverter','direct_prev_photo','direct_curr_photo','inverter_prev_photo','inverter_curr_photo'] as $f) {
                if (!empty($row[$f])) {
                    $path = __DIR__ . '/' . $row[$f];
                    if (is_file($path)) @unlink($path);
                }
            }
        }
        $stmt = $db->prepare("DELETE FROM electricity_bills WHERE id = ?");
        $stmt->execute([$id]);
    }
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// GLOBAL expenses PDF
if (isset($_GET['download_pdf'])) {
    if (file_exists(__DIR__ . '/fpdf.php')) {
        require_once __DIR__ . '/fpdf.php';
    } else {
        require_once '/usr/share/php/fpdf/fpdf.php';
    }

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(0,10,'House Expenses Report',0,1,'C');

    $pdf->SetFont('Arial','',10);
    $pdf->Ln(2);
    $pdf->Cell(0,6,'Generated on: ' . date('Y-m-d H:i'),0,1);

    $pdf->Ln(5);
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(30,6,'Date',1);
    $pdf->Cell(30,6,'Amount (Rs)',1);
    $pdf->Cell(50,6,'Paid By',1);
    $pdf->Cell(80,6,'Note',1);
    $pdf->Ln();

    $pdf->SetFont('Arial','',9);

    $stmt = $db->query("
        SELECT e.*, m.name AS payer_name
        FROM expenses e
        LEFT JOIN members m ON e.paid_by = m.id
        ORDER BY date ASC, id ASC
    ");
    while ($e = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $date  = $e['date'];
        $amt   = number_format($e['amount'], 2);
        $payer = $e['payer_name'] ?: '-';
        $note  = $e['note'];

        $pdf->Cell(30,6,$date,1);
        $pdf->Cell(30,6,$amt,1);
        $pdf->Cell(50,6,substr($payer,0,25),1);
        $pdf->Cell(80,6,substr($note,0,45),1);
        $pdf->Ln();
    }

    $pdf->Output('D','expenses_report.pdf');
    exit;
}

// OLD per-person PDF (keep for safety, but UI ab use nahi kar raha)
if (isset($_GET['person_pdf'])) {
    $mid = (int)($_GET['member_id'] ?? 0);
    $month = $_GET['month'] ?? '';
    if ($mid > 0) {
        if (file_exists(__DIR__ . '/fpdf.php')) {
            require_once __DIR__ . '/fpdf.php';
        } else {
            require_once '/usr/share/php/fpdf/fpdf.php';
        }

        $memberName = isset($memberNames[$mid]) ? $memberNames[$mid] : ('ID '.$mid);

        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial','B',14);

        $titleMonth = ($month && preg_match('/^\d{4}-\d{2}$/',$month)) ? $month : 'All Time';

        $pdf->Cell(0,8,'Expense Report: '.$memberName,0,1,'C');
        $pdf->SetFont('Arial','',10);
        $pdf->Cell(0,6,'Period: '.$titleMonth,0,1,'C');
        $pdf->Ln(4);

        $pdf->SetFont('Arial','B',9);
        $pdf->Cell(25,6,'Date',1);
        $pdf->Cell(85,6,'Note',1);
        $pdf->Cell(30,6,'Total Expense',1);
        $pdf->Cell(30,6,'Share (Rs)',1);
        $pdf->Ln();
        $pdf->SetFont('Arial','',8);

        if ($month && preg_match('/^\d{4}-\d{2}$/',$month)) {
            $prefix = $month . '%';
            $stmt = $db->prepare("
                SELECT e.date, e.amount, e.note, ep.share
                FROM expenses e
                JOIN expense_participants ep ON ep.expense_id = e.id
                WHERE ep.member_id = ? AND e.date LIKE ?
                ORDER BY e.date ASC, e.id ASC
            ");
            $stmt->execute([$mid, $prefix]);
        } else {
            $stmt = $db->prepare("
                SELECT e.date, e.amount, e.note, ep.share
                FROM expenses e
                JOIN expense_participants ep ON ep.expense_id = e.id
                WHERE ep.member_id = ?
                ORDER BY e.date ASC, e.id ASC
            ");
            $stmt->execute([$mid]);
        }

        $totalShare = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $totalShare += $row['share'];
            $pdf->Cell(25,6,$row['date'],1);
            $pdf->Cell(85,6,substr($row['note'],0,40),1);
            $pdf->Cell(30,6,number_format($row['amount'],2),1);
            $pdf->Cell(30,6,number_format($row['share'],2),1);
            $pdf->Ln();
        }

        $pdf->Ln(4);
        $pdf->SetFont('Arial','B',11);
        $pdf->Cell(0,6,'Total Share = Rs '.number_format($totalShare,2),0,1,'R');

        $fname = 'person_'.$mid.'_'.($month ?: 'all').'.pdf';
        $pdf->Output('D',$fname);
        exit;
    }
}

// ELECTRIC BILL PDF (one bill)
if (isset($_GET['bill_pdf'])) {
    $bid = (int)($_GET['bill_pdf'] ?? 0);
    if ($bid > 0) {
        $stmt = $db->prepare("SELECT * FROM electricity_bills WHERE id = ?");
        $stmt->execute([$bid]);
        $b = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($b) {
            if (file_exists(__DIR__ . '/fpdf.php')) {
                require_once __DIR__ . '/fpdf.php';
            } else {
                require_once '/usr/share/php/fpdf/fpdf.php';
            }

            $pdf = new FPDF();
            $pdf->AddPage();
            $pdf->SetFont('Arial','B',14);
            $pdf->Cell(0,8,'Electricity Bill - '.$b['month_label'],0,1,'C');

            $pdf->SetFont('Arial','',10);
            $pdf->Cell(0,6,'Period: '.$b['start_date'].' to '.$b['end_date'],0,1);
            $pdf->Ln(2);

            $pdf->SetFont('Arial','B',10);
            $pdf->Cell(50,6,'',0);
            $pdf->Cell(30,6,'Prev',1);
            $pdf->Cell(30,6,'Current',1);
            $pdf->Cell(30,6,'Units',1);
            $pdf->Ln();

            $pdf->SetFont('Arial','',10);

            $pdf->Cell(50,6,'Direct Meter',0);
            $pdf->Cell(30,6,$b['direct_prev'],1);
            $pdf->Cell(30,6,$b['direct_curr'],1);
            $pdf->Cell(30,6,$b['direct_units'],1);
            $pdf->Ln();

            $pdf->Cell(50,6,'Inverter Meter',0);
            $pdf->Cell(30,6,$b['inverter_prev'],1);
            $pdf->Cell(30,6,$b['inverter_curr'],1);
            $pdf->Cell(30,6,$b['inverter_units'],1);
            $pdf->Ln(8);

            $pdf->Cell(0,6,'Per Unit Rate: Rs '.$b['unit_rate'],0,1);
            // $pdf->Cell(0,6,'Total Units: '.($b['direct_units'] + $b['inverter_units']),0,1);
            // $pdf->Cell(0,6,'Total Amount: Rs '.$b['total_amount'],0,1);
            $direct_amt = $b['direct_units'] * $b['unit_rate'];
            $inv_total  = $b['inverter_units'] * $b['unit_rate'];
            $inv_tenant = $inv_total / 2;
            $inv_owner  = $inv_total / 2;

            $pdf->Cell(0,6,'Direct Meter Amount: Rs '.$direct_amt,0,1);
            $pdf->Cell(0,6,'Inverter Meter Total: Rs '.$inv_total,0,1);
            $pdf->Cell(0,6,'Inverter (My 50%): Rs '.$inv_tenant,0,1);
            $pdf->Cell(0,6,'Inverter (Owner 50%): Rs '.$inv_owner,0,1);
            $pdf->Ln(2);
            $pdf->SetFont('Arial','B',11);
            $pdf->Cell(0,6,'Total Amount (I Pay): Rs '.$b['total_amount'],0,1);


            if (!empty($b['note'])) {
                $pdf->Ln(2);
                $pdf->MultiCell(0,5,'Note: '.$b['note']);
            }

            $pdf->Ln(5);
            $pdf->SetFont('Arial','B',10);
            $pdf->Cell(0,6,'Meter Photos:',0,1);

            $imgWidth = 40;
            $y = $pdf->GetY() + 2;
            $x1 = 10;
            $x2 = 110;

            if (!empty($b['direct_prev_photo']) || !empty($b['direct_curr_photo'])) {
                $pdf->SetFont('Arial','',9);
                if (!empty($b['direct_prev_photo'])) {
                    $path = __DIR__ . '/' . $b['direct_prev_photo'];
                    if (is_file($path)) {
                        $pdf->Text($x1, $y, 'Direct Prev');
                        $pdf->Image($path, $x1, $y+2, $imgWidth, 0);
                    }
                }
                if (!empty($b['direct_curr_photo'])) {
                    $path = __DIR__ . '/' . $b['direct_curr_photo'];
                    if (is_file($path)) {
                        $pdf->Text($x2, $y, 'Direct Curr');
                        $pdf->Image($path, $x2, $y+2, $imgWidth, 0);
                    }
                }
                $y += 45;
            }

            if (!empty($b['inverter_prev_photo']) || !empty($b['inverter_curr_photo'])) {
                $pdf->SetFont('Arial','',9);
                if (!empty($b['inverter_prev_photo'])) {
                    $path = __DIR__ . '/' . $b['inverter_prev_photo'];
                    if (is_file($path)) {
                        $pdf->Text($x1, $y, 'Inverter Prev');
                        $pdf->Image($path, $x1, $y+2, $imgWidth, 0);
                    }
                }
                if (!empty($b['inverter_curr_photo'])) {
                    $path = __DIR__ . '/' . $b['inverter_curr_photo'];
                    if (is_file($path)) {
                        $pdf->Text($x2, $y, 'Inverter Curr');
                        $pdf->Image($path, $x2, $y+2, $imgWidth, 0);
                    }
                }
            }

            $pdf->Output('D','electricity_bill_'.$bid.'.pdf');
            exit;
        }
    }
}

// REPORT PDF (Ghar + members, all/month/range)
if (isset($_GET['report_pdf'])) {
    $reportMemberRaw = $_GET['report_member'] ?? '';
    $filterType      = $_GET['filter_type'] ?? 'all';
    $reportMonth     = $_GET['report_month'] ?? '';
    $reportFrom      = $_GET['report_from'] ?? '';
    $reportTo        = $_GET['report_to'] ?? '';

    $validFilterTypes = ['all','month','range'];
    if (!in_array($filterType, $validFilterTypes, true)) {
        $filterType = 'all';
    }

    $reportIsHouse = ($reportMemberRaw === 'house');
    $reportMemberId = $reportIsHouse ? 0 : (int)$reportMemberRaw;

    if (!$reportIsHouse && $reportMemberId <= 0) {
        // invalid, just go back
        header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    $reportValidMonth = ($reportMonth && preg_match('/^\d{4}-\d{2}$/',$reportMonth));
    $reportValidFrom  = ($reportFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/',$reportFrom));
    $reportValidTo    = ($reportTo && preg_match('/^\d{4}-\d{2}-\d{2}$/',$reportTo));

    if (file_exists(__DIR__ . '/fpdf.php')) {
        require_once __DIR__ . '/fpdf.php';
    } else {
        require_once '/usr/share/php/fpdf/fpdf.php';
    }

    $titleName = $reportIsHouse ? 'Ghar (All house kharcha)'
                                : ($memberNames[$reportMemberId] ?? ('Member '.$reportMemberId));

    $periodLabel = 'All Time';
    if ($filterType === 'range') {
        $fromLabel = $reportValidFrom ? $reportFrom : '...';
        $toLabel   = $reportValidTo ? $reportTo : '...';
        $periodLabel = $fromLabel.' to '.$toLabel;
    } elseif ($filterType === 'month' && $reportValidMonth) {
        $periodLabel = $reportMonth;
    }

    $sql = '';
    $params = [];
    if ($reportIsHouse) {
        $sql = "SELECT e.date, e.amount, e.note FROM expenses e";
    } else {
        $sql = "
            SELECT e.date, e.amount, e.note, ep.share
            FROM expenses e
            JOIN expense_participants ep ON ep.expense_id = e.id
        ";
        $params[] = $reportMemberId;
    }

    $where = [];
    if (!$reportIsHouse) {
        $where[] = "ep.member_id = ?";
    }

    if ($filterType === 'range') {
        if ($reportValidFrom) {
            $where[] = "e.date >= ?";
            $params[] = $reportFrom;
        }
        if ($reportValidTo) {
            $where[] = "e.date <= ?";
            $params[] = $reportTo;
        }
    } elseif ($filterType === 'month' && $reportValidMonth) {
        $where[] = "e.date LIKE ?";
        $params[] = $reportMonth.'%';
    }
    // 'all' => koi extra where nahi

    if ($where) {
        $sql .= " WHERE ".implode(" AND ", $where);
    }
    $sql .= " ORDER BY e.date ASC, e.id ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $rows = [];
    $total = 0;
    if ($reportIsHouse) {
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $r['share'] = $r['amount'];
            $total += $r['share'];
            $rows[] = $r;
        }
    } else {
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $total += $r['share'];
            $rows[] = $r;
        }
    }

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',14);
    $pdf->Cell(0,8,'Expense Report: '.$titleName,0,1,'C');
    $pdf->SetFont('Arial','',10);
    $pdf->Cell(0,6,'Period: '.$periodLabel,0,1,'C');
    $pdf->Ln(4);

    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(25,6,'Date',1);
    $pdf->Cell(85,6,'Note',1);
    $pdf->Cell(30,6,'Total Expense',1);
    $pdf->Cell(30,6, $reportIsHouse ? 'Amount (Rs)' : 'Share (Rs)',1);
    $pdf->Ln();
    $pdf->SetFont('Arial','',8);

    foreach ($rows as $r) {
        $pdf->Cell(25,6,$r['date'],1);
        $pdf->Cell(85,6,substr($r['note'],0,40),1);
        $pdf->Cell(30,6,number_format($r['amount'],2),1);
        $pdf->Cell(30,6,number_format($r['share'],2),1);
        $pdf->Ln();
    }

    $pdf->Ln(4);
    $pdf->SetFont('Arial','B',11);
    $label = $reportIsHouse ? 'Total ghar kharcha = Rs ' : 'Total share = Rs ';
    $pdf->Cell(0,6, $label . number_format($total,2),0,1,'R');

    $fname = 'report_'.($reportIsHouse ? 'ghar' : 'member'.$reportMemberId).'_'.$filterType.'.pdf';
    $pdf->Output('D',$fname);
    exit;
}

// HANDLE POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_contribution') {
        $member_id = (int)($_POST['member_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $date = $_POST['date'] ?: date('Y-m-d');
        $note = trim($_POST['note'] ?? '');
        $attachment = save_upload('attachment', 'contrib');

        if ($member_id > 0 && $amount > 0) {
            $stmt = $db->prepare("INSERT INTO contributions (member_id, amount, date, note, attachment) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$member_id, $amount, $date, $note, $attachment]);
        }

    } elseif ($action === 'add_expense') {
        $amount = (float)($_POST['amount'] ?? 0);
        $date = $_POST['date'] ?: date('Y-m-d');
        $note = trim($_POST['note'] ?? '');
        $paid_by = (int)($_POST['paid_by'] ?? 0);
        $participants = $_POST['participants'] ?? [];
        $attachment = save_upload('attachment', 'expense');

        if ($amount > 0) {
            if (empty($participants)) {
                $participants = array_column($members, 'id');
            }
            $participants = array_map('intval', $participants);
            $count = count($participants);
            if ($count > 0) {
                $share = $amount / $count;

                $stmt = $db->prepare("INSERT INTO expenses (amount, date, note, paid_by, attachment) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$amount, $date, $note, $paid_by > 0 ? $paid_by : null, $attachment]);
                $expense_id = $db->lastInsertId();

                $pstmt = $db->prepare("INSERT INTO expense_participants (expense_id, member_id, share) VALUES (?, ?, ?)");
                foreach ($participants as $pid) {
                    $pstmt->execute([$expense_id, $pid, $share]);
                }
            }
        }

    } elseif ($action === 'update_contribution' && $isAdmin) {
        $id = (int)($_POST['id'] ?? 0);
        $member_id = (int)($_POST['member_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $date = $_POST['date'] ?: date('Y-m-d');
        $note = trim($_POST['note'] ?? '');
        $existing = $_POST['existing_attachment'] ?? null;
        $newFile = save_upload('attachment', 'contrib');

        $attachment = $existing;
        if ($newFile) {
            if (!empty($existing)) {
                $path = __DIR__ . '/' . $existing;
                if (is_file($path)) @unlink($path);
            }
            $attachment = $newFile;
        }

        if ($id > 0 && $member_id > 0 && $amount > 0) {
            $stmt = $db->prepare("UPDATE contributions SET member_id = ?, amount = ?, date = ?, note = ?, attachment = ? WHERE id = ?");
            $stmt->execute([$member_id, $amount, $date, $note, $attachment, $id]);
        }

    } elseif ($action === 'update_expense' && $isAdmin) {
        $id = (int)($_POST['id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $date = $_POST['date'] ?: date('Y-m-d');
        $note = trim($_POST['note'] ?? '');
        $paid_by = (int)($_POST['paid_by'] ?? 0);
        $participants = $_POST['participants'] ?? [];
        $existing = $_POST['existing_attachment'] ?? null;
        $newFile = save_upload('attachment', 'expense');

        $attachment = $existing;
        if ($newFile) {
            if (!empty($existing)) {
                $path = __DIR__ . '/' . $existing;
                if (is_file($path)) @unlink($path);
            }
            $attachment = $newFile;
        }

        if ($id > 0 && $amount > 0) {
            if (empty($participants)) {
                $participants = array_column($members, 'id');
            }
            $participants = array_map('intval', $participants);
            $count = count($participants);

            if ($count > 0) {
                $share = $amount / $count;

                $stmt = $db->prepare("UPDATE expenses SET amount = ?, date = ?, note = ?, paid_by = ?, attachment = ? WHERE id = ?");
                $stmt->execute([$amount, $date, $note, $paid_by > 0 ? $paid_by : null, $attachment, $id]);

                $stmt = $db->prepare("DELETE FROM expense_participants WHERE expense_id = ?");
                $stmt->execute([$id]);

                $pstmt = $db->prepare("INSERT INTO expense_participants (expense_id, member_id, share) VALUES (?, ?, ?)");
                foreach ($participants as $pid) {
                    $pstmt->execute([$id, $pid, $share]);
                }
            }
        }

    } elseif ($action === 'add_bill') {
        $month_label   = trim($_POST['month_label'] ?? '');
        $start_date    = $_POST['start_date'] ?: null;
        $end_date      = $_POST['end_date'] ?: null;
        $direct_prev   = (float)($_POST['direct_prev'] ?? 0);
        $direct_curr   = (float)($_POST['direct_curr'] ?? 0);
        $inverter_prev = (float)($_POST['inverter_prev'] ?? 0);
        $inverter_curr = (float)($_POST['inverter_curr'] ?? 0);
        $unit_rate     = (float)($_POST['unit_rate'] ?? 10);
        $note          = trim($_POST['note'] ?? '');

        $dp_photo = save_upload('direct_prev_photo', 'el_dp');
        $dc_photo = save_upload('direct_curr_photo', 'el_dc');
        $ip_photo = save_upload('inverter_prev_photo', 'el_ip');
        $ic_photo = save_upload('inverter_curr_photo', 'el_ic');

        // $d_units = max(0, $direct_curr - $direct_prev);
        // $i_units = max(0, $inverter_curr - $inverter_prev);
        // $total_units = $d_units + $i_units;
        // $total_amount = $total_units * $unit_rate;

        $d_units = max(0, $direct_curr - $direct_prev);
        $i_units = max(0, $inverter_curr - $inverter_prev);

        // Direct meter full amount
        $direct_amount = $d_units * $unit_rate;

        // Inverter meter split 50-50
        $inverter_total_amount = $i_units * $unit_rate;
        $inverter_tenant_amount = $inverter_total_amount / 2; // your share
        $inverter_owner_amount  = $inverter_total_amount / 2; // owner share (info only)

        // Only your share added to total
        $total_amount = $direct_amount + $inverter_tenant_amount;


        if (!empty($month_label) && $end_date) {
            $stmt = $db->prepare("
                INSERT INTO electricity_bills 
                (month_label, start_date, end_date, direct_prev, direct_curr, inverter_prev, inverter_curr, direct_units, inverter_units, unit_rate, total_amount, note, direct_prev_photo, direct_curr_photo, inverter_prev_photo, inverter_curr_photo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$month_label, $start_date, $end_date, $direct_prev, $direct_curr, $inverter_prev, $inverter_curr, $d_units, $i_units, $unit_rate, $total_amount, $note, $dp_photo, $dc_photo, $ip_photo, $ic_photo]);
        }

    } elseif ($action === 'update_bill' && $isAdmin) {
        $id           = (int)($_POST['id'] ?? 0);
        $month_label  = trim($_POST['month_label'] ?? '');
        $start_date   = $_POST['start_date'] ?: null;
        $end_date     = $_POST['end_date'] ?: null;
        $direct_prev  = (float)($_POST['direct_prev'] ?? 0);
        $direct_curr  = (float)($_POST['direct_curr'] ?? 0);
        $inverter_prev= (float)($_POST['inverter_prev'] ?? 0);
        $inverter_curr= (float)($_POST['inverter_curr'] ?? 0);
        $unit_rate    = (float)($_POST['unit_rate'] ?? 10);
        $note         = trim($_POST['note'] ?? '');

        $existing_dp = $_POST['existing_dp'] ?? null;
        $existing_dc = $_POST['existing_dc'] ?? null;
        $existing_ip = $_POST['existing_ip'] ?? null;
        $existing_ic = $_POST['existing_ic'] ?? null;

        $new_dp = save_upload('direct_prev_photo', 'el_dp');
        $new_dc = save_upload('direct_curr_photo', 'el_dc');
        $new_ip = save_upload('inverter_prev_photo', 'el_ip');
        $new_ic = save_upload('inverter_curr_photo', 'el_ic');

        $dp_photo = $existing_dp;
        if ($new_dp) {
            if (!empty($existing_dp)) {
                $path = __DIR__ . '/' . $existing_dp;
                if (is_file($path)) @unlink($path);
            }
            $dp_photo = $new_dp;
        }

        $dc_photo = $existing_dc;
        if ($new_dc) {
            if (!empty($existing_dc)) {
                $path = __DIR__ . '/' . $existing_dc;
                if (is_file($path)) @unlink($path);
            }
            $dc_photo = $new_dc;
        }

        $ip_photo = $existing_ip;
        if ($new_ip) {
            if (!empty($existing_ip)) {
                $path = __DIR__ . '/' . $existing_ip;
                if (is_file($path)) @unlink($path);
            }
            $ip_photo = $new_ip;
        }

        $ic_photo = $existing_ic;
        if ($new_ic) {
            if (!empty($existing_ic)) {
                $path = __DIR__ . '/' . $existing_ic;
                if (is_file($path)) @unlink($path);
            }
            $ic_photo = $new_ic;
        }

        // $d_units = max(0, $direct_curr - $direct_prev);
        // $i_units = max(0, $inverter_curr - $inverter_prev);
        // $total_units = $d_units + $i_units;
        // $total_amount = $total_units * $unit_rate;
        $d_units = max(0, $direct_curr - $direct_prev);
        $i_units = max(0, $inverter_curr - $inverter_prev);

        $direct_amount = $d_units * $unit_rate;

        $inverter_total_amount = $i_units * $unit_rate;
        $inverter_tenant_amount = $inverter_total_amount / 2;
        $inverter_owner_amount  = $inverter_total_amount / 2;

        $total_amount = $direct_amount + $inverter_tenant_amount;



        if ($id > 0 && !empty($month_label) && $end_date) {
            $stmt = $db->prepare("
                UPDATE electricity_bills
                SET month_label = ?, start_date = ?, end_date = ?, direct_prev = ?, direct_curr = ?, 
                    inverter_prev = ?, inverter_curr = ?, direct_units = ?, inverter_units = ?, 
                    unit_rate = ?, total_amount = ?, note = ?, 
                    direct_prev_photo = ?, direct_curr_photo = ?, inverter_prev_photo = ?, inverter_curr_photo = ?
                WHERE id = ?
            ");
            $stmt->execute([$month_label, $start_date, $end_date, $direct_prev, $direct_curr, $inverter_prev, $inverter_curr, $d_units, $i_units, $unit_rate, $total_amount, $note, $dp_photo, $dc_photo, $ip_photo, $ic_photo, $id]);
        }
    }

    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// BALANCES
$contribTotals = [];
$stmt = $db->query("SELECT member_id, COALESCE(SUM(amount),0) AS total FROM contributions GROUP BY member_id");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $contribTotals[$row['member_id']] = $row['total'];
}

$shareTotals = [];
$stmt = $db->query("SELECT member_id, COALESCE(SUM(share),0) AS total FROM expense_participants GROUP BY member_id");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $shareTotals[$row['member_id']] = $row['total'];
}

$totalContrib = 0;
foreach ($contribTotals as $v) $totalContrib += $v;

$stmt = $db->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses");
$totalSpent = (float)$stmt->fetch(PDO::FETCH_ASSOC)['t'];

$poolLeft = $totalContrib - $totalSpent;

// RECENT
$recentContrib = $db->query("
    SELECT c.*, m.name AS member_name
    FROM contributions c
    JOIN members m ON c.member_id = m.id
    ORDER BY date DESC, id DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$recentExpenses = $db->query("
    SELECT e.*, m.name AS payer_name
    FROM expenses e
    LEFT JOIN members m ON e.paid_by = m.id
    ORDER BY date DESC, id DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// ELECTRICITY
$electricBills = $db->query("
    SELECT * FROM electricity_bills
    ORDER BY end_date DESC, id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$lastBill = !empty($electricBills) ? $electricBills[0] : null;

// EDIT MODES
$editContribution = null;
if ($isAdmin && isset($_GET['edit_contribution'])) {
    $id = (int)$_GET['edit_contribution'];
    if ($id > 0) {
        $stmt = $db->prepare("
            SELECT c.*, m.name AS member_name 
            FROM contributions c 
            JOIN members m ON c.member_id = m.id 
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $editContribution = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$editExpense = null;
$editExpenseParticipants = [];
if ($isAdmin && isset($_GET['edit_expense'])) {
    $id = (int)$_GET['edit_expense'];
    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM expenses WHERE id = ?");
        $stmt->execute([$id]);
        $editExpense = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($editExpense) {
            $pstmt = $db->prepare("
                SELECT ep.*, m.name AS member_name
                FROM expense_participants ep
                JOIN members m ON ep.member_id = m.id
                WHERE ep.expense_id = ?
            ");
            $pstmt->execute([$id]);
            $rows = $pstmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $editExpenseParticipants[] = (int)$r['member_id'];
            }
        }
    }
}

$editBill = null;
if ($isAdmin && isset($_GET['edit_bill'])) {
    $bid = (int)$_GET['edit_bill'];
    if ($bid > 0) {
        $stmt = $db->prepare("SELECT * FROM electricity_bills WHERE id = ?");
        $stmt->execute([$bid]);
        $editBill = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// REPORT (HTML) VARIABLES
$reportMemberRaw = $_GET['report_member'] ?? '';
$filterType      = $_GET['filter_type'] ?? 'all';
$reportMonth     = $_GET['report_month'] ?? '';
$reportFrom      = $_GET['report_from'] ?? '';
$reportTo        = $_GET['report_to'] ?? '';

$validFilterTypes = ['all','month','range'];
if (!in_array($filterType, $validFilterTypes, true)) {
    $filterType = 'all';
}

$reportIsHouse = ($reportMemberRaw === 'house');
$reportMemberId = $reportIsHouse ? 0 : (int)$reportMemberRaw;

$reportValidMonth = ($reportMonth && preg_match('/^\d{4}-\d{2}$/',$reportMonth));
$reportValidFrom  = ($reportFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/',$reportFrom));
$reportValidTo    = ($reportTo && preg_match('/^\d{4}-\d{2}-\d{2}$/',$reportTo));

$personRows = [];
$personTotalShare = 0;
$reportPeriodLabel = 'All Time';

if ($filterType === 'range') {
    $fromLabel = $reportValidFrom ? $reportFrom : '...';
    $toLabel   = $reportValidTo ? $reportTo : '...';
    $reportPeriodLabel = $fromLabel.' to '.$toLabel;
} elseif ($filterType === 'month' && $reportValidMonth) {
    $reportPeriodLabel = $reportMonth;
}

// Build report only if member selected OR ghar selected
if ($reportIsHouse || $reportMemberId > 0) {
    $sql = '';
    $params = [];

    if ($reportIsHouse) {
        $sql = "SELECT e.date, e.amount, e.note FROM expenses e";
    } else {
        $sql = "
            SELECT e.date, e.amount, e.note, ep.share
            FROM expenses e
            JOIN expense_participants ep ON ep.expense_id = e.id
        ";
        $params[] = $reportMemberId;
    }

    $where = [];
    if (!$reportIsHouse) {
        $where[] = "ep.member_id = ?";
    }

    if ($filterType === 'range') {
        if ($reportValidFrom) {
            $where[] = "e.date >= ?";
            $params[] = $reportFrom;
        }
        if ($reportValidTo) {
            $where[] = "e.date <= ?";
            $params[] = $reportTo;
        }
    } elseif ($filterType === 'month' && $reportValidMonth) {
        $where[] = "e.date LIKE ?";
        $params[] = $reportMonth.'%';
    }
    // 'all' => koi extra filter nahi

    if ($where) {
        $sql .= " WHERE ".implode(" AND ", $where);
    }
    $sql .= " ORDER BY e.date ASC, e.id ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    if ($reportIsHouse) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['share'] = $row['amount'];
            $personTotalShare += $row['share'];
            $personRows[] = $row;
        }
    } else {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $personTotalShare += $row['share'];
            $personRows[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ghar ka Kharcha - House Expense Manager</title>
    <style>
        :root {
            --bg: #020617;
            --bg-card: #0b1120;
            --border: #1f2937;
            --text: #e5e7eb;
            --text-muted: #9ca3af;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --danger: #ef4444;
            --danger-hover: #dc2626;
        }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: var(--bg); margin: 0; padding: 0; color: var(--text); }
        header { background: #020617; border-bottom: 1px solid var(--border); color: var(--text); padding: 10px 20px; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:10; }
        header h1 { margin: 0; font-size: 20px; }
        .status { font-size: 13px; }
        .container { max-width: 1180px; margin: 20px auto; padding: 0 15px 30px; }
        .card { background: var(--bg-card); border-radius: 10px; padding: 15px 18px; margin-bottom: 20px; box-shadow: 0 10px 25px rgba(15,23,42,0.5); border: 1px solid var(--border); }
        h2 { margin-top: 0; font-size: 17px; border-bottom: 1px solid var(--border); padding-bottom: 5px; display:flex; justify-content:space-between; align-items:center; }
        form { margin-top: 10px; }
        label { display: block; margin-top: 8px; font-size: 13px; color: var(--text-muted); }
        input[type="number"], input[type="date"], input[type="text"], select, textarea {
            width: 100%; padding: 6px 8px; margin-top: 3px; border-radius: 6px; border: 1px solid var(--border);
            font-size: 13px; background: #020617; color: var(--text);
        }
        textarea { resize: vertical; }
        input::placeholder, textarea::placeholder { color: #6b7280; }
        .btn {
            display: inline-block; margin-top: 10px; padding: 6px 12px; border-radius: 999px; border: none;
            cursor: pointer; font-size: 13px; background: var(--accent); color: white; text-decoration:none;
        }
        .btn:hover { background: var(--accent-hover); }
        .btn-sm { padding: 4px 9px; font-size: 12px; }
        .btn-danger { background: var(--danger); }
        .btn-danger:hover { background: var(--danger-hover); }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 13px; }
        th, td { border: 1px solid var(--border); padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #020617; color: var(--text-muted); }
        .flex { display: flex; gap: 15px; flex-wrap: wrap; }
        .flex > .card { flex: 1 1 320px; }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; background: #1f2937; color: var(--text-muted); }
        .amount-pos { color: #22c55e; }
        .amount-neg { color: #f97316; }
        .checkbox-group { display: flex; flex-wrap: wrap; gap: 6px 16px; margin-top: 5px; }
        .checkbox-group label { display: flex; align-items: center; margin-top: 0; font-size: 12px; color: var(--text); }
        .checkbox-group input { margin-right: 4px; }
        small { color: var(--text-muted); }
        .actions a { margin-right: 4px; display:inline-block; }
        .login-form { display:flex; gap:6px; align-items:center; }
        .login-form input[type="password"] { padding:4px 7px; font-size:12px; border-radius:999px; border:1px solid var(--border); background:#020617; color:var(--text); }
        .attachment-link { font-size:12px; display:inline-block; margin-top:4px; color:#60a5fa; text-decoration:none; }
        .attachment-link:hover { text-decoration:underline; }
        .tag { font-size:11px; padding:2px 6px; border-radius:999px; background:#1f2937; margin-right:4px; }
        .row-title { font-weight:bold; color:var(--text); }
        .muted { color: var(--text-muted); }
        .section-grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(320px,1fr)); gap:15px; }
        .meter-box { border:1px solid var(--border); border-radius:8px; padding:8px 10px; margin-top:6px; }
        .meter-title { font-size:13px; font-weight:bold; color:var(--text); margin-bottom:4px; }
        .inline-row { display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; }
        .inline-row > div { flex:1 1 140px; }
    </style>
</head>
<body>
<header>
    <h1>Ghar ka Kharcha 💸</h1>
    <div class="status">
        <?php if ($isAdmin): ?>
            <span class="pill" style="background:#16a34a;color:#e5e7eb;">ADMIN MODE</span>
            <a href="?logout=1" class="btn btn-sm" style="margin-left:8px;">Logout</a>
        <?php else: ?>
            <span class="pill">User View (no edit/delete)</span>
            <form method="post" class="login-form" style="display:inline-flex;margin-left:8px;">
                <input type="hidden" name="action" value="login">
                <input type="password" name="password" placeholder="Admin password" required>
                <button class="btn btn-sm" type="submit">Admin Login</button>
            </form>
            <?php if (!empty($loginError)): ?>
                <div style="color:#fecaca;font-size:12px;"><?php echo h($loginError); ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</header>

<div class="container">

    <!-- SUMMARY -->
    <div class="card">
        <h2>Summary</h2>
        <p>Total Pool Add (sab ka milake): <strong><?php echo number_format($totalContrib, 2); ?></strong> ₹</p>
        <p>Total Spent (grocery, etc.): <strong><?php echo number_format($totalSpent, 2); ?></strong> ₹</p>
        <p>Pool Left (bacha hua): <strong><?php echo number_format($poolLeft, 2); ?></strong> ₹</p>
        <small>All data local SQLite DB (expenses.db) me store hai. Server = tumhara laptop / LAN only.</small>
    </div>

    <!-- BALANCES + REPORT FILTER -->
    <div class="card">
        <h2>
            <span>Per Person Balance</span>
            <span class="muted" style="font-size:12px;">Contribution - Share</span>
        </h2>
        <table>
            <tr>
                <th>Naam</th>
                <th>Total Diya (₹)</th>
                <th>Unka Kharcha Share (₹)</th>
                <th>Final Balance (₹)</th>
            </tr>
            <?php foreach ($members as $m):
                $mid = $m['id'];
                $given = $contribTotals[$mid] ?? 0;
                $used = $shareTotals[$mid] ?? 0;
                $balance = $given - $used;
            ?>
            <tr>
                <td><?php echo h($m['name']); ?></td>
                <td><?php echo number_format($given, 2); ?></td>
                <td><?php echo number_format($used, 2); ?></td>
                <td class="<?php echo $balance >= 0 ? 'amount-pos' : 'amount-neg'; ?>">
                    <?php echo number_format($balance, 2); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <small>Positive = zyada diya / kam use hua, Negative = kam diya / zyada use hua.</small>

        <hr style="border:0;border-top:1px solid var(--border);margin:12px 0;">

        <!-- REPORT FILTER FORM -->
        <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
            <div style="min-width:180px;">
                <label>Monthly / Full report kiske liye?</label>
                <select name="report_member">
                    <option value="">-- Select member --</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php if (!$reportIsHouse && $reportMemberId==$m['id']) echo 'selected'; ?>>
                            <?php echo h($m['name']); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="house" <?php if ($reportIsHouse) echo 'selected'; ?>>
                        Ghar (All house kharcha)
                    </option>
                </select>
            </div>
            <div style="min-width:140px;">
                <label>Filter type</label>
                <select name="filter_type">
                    <option value="all"   <?php if ($filterType==='all') echo 'selected'; ?>>All kharch</option>
                    <option value="month" <?php if ($filterType==='month') echo 'selected'; ?>>Month</option>
                    <option value="range" <?php if ($filterType==='range') echo 'selected'; ?>>Date range</option>
                </select>
            </div>
            <div style="min-width:140px;">
                <label>Month (YYYY-MM) (optional)</label>
                <input type="text" name="report_month" placeholder="2025-12" value="<?php echo h($reportMonth); ?>">
            </div>
            <div style="min-width:140px;">
                <label>From date (optional)</label>
                <input type="date" name="report_from" value="<?php echo h($reportFrom); ?>">
            </div>
            <div style="min-width:140px;">
                <label>To date (optional)</label>
                <input type="date" name="report_to" value="<?php echo h($reportTo); ?>">
            </div>
            <div>
                <button class="btn btn-sm" type="submit">View Report</button>
                <?php if ($reportIsHouse || $reportMemberId > 0): ?>
                    <a class="btn btn-sm"
                       href="?report_pdf=1&report_member=<?php echo h($reportIsHouse ? 'house' : (string)$reportMemberId); ?>
&filter_type=<?php echo h($filterType); ?>&report_month=<?php echo h($reportMonth); ?>&report_from=<?php echo h($reportFrom); ?>&report_to=<?php echo h($reportTo); ?>">
                        Download PDF
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($reportIsHouse || $reportMemberId > 0): ?>
            <div style="margin-top:12px;">
                <div class="row-title">
                    <?php
                        if ($reportIsHouse) {
                            echo 'Ghar (All house kharcha)';
                        } else {
                            echo h($memberNames[$reportMemberId] ?? ('Member '.$reportMemberId));
                        }
                    ?>
                    – <?php echo h($reportPeriodLabel); ?> report
                </div>
                <?php if (empty($personRows)): ?>
                    <small class="muted">Is filter ke liye koi expense nahi mila.</small>
                <?php else: ?>
                    <table style="margin-top:8px;">
                        <tr>
                            <th>Date</th>
                            <th>Note</th>
                            <th>Total Expense (₹)</th>
                            <th><?php echo $reportIsHouse ? 'Amount (₹)' : 'Share (₹)'; ?></th>
                        </tr>
                        <?php foreach ($personRows as $r): ?>
                            <tr>
                                <td><?php echo h($r['date']); ?></td>
                                <td><?php echo nl2br(h($r['note'])); ?></td>
                                <td><?php echo number_format($r['amount'],2); ?></td>
                                <td><?php echo number_format($r['share'],2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    <p style="margin-top:6px;">
                        <strong><?php echo $reportIsHouse ? 'Total ghar kharcha:' : 'Total share:'; ?></strong>
                        <?php echo number_format($personTotalShare,2); ?> ₹
                    </p>
                    <small class="muted">
                        Filter type decide karega:
                        All = full history, Month = sirf us month ke data, Date range = From–To ke beech ka data.
                    </small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- EDIT FORMS (ADMIN) -->
    <?php if ($isAdmin && $editContribution): ?>
    <div class="card" id="edit_contribution">
        <h2>Edit Contribution (<?php echo h($editContribution['member_name']); ?>)</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_contribution">
            <input type="hidden" name="id" value="<?php echo (int)$editContribution['id']; ?>">
            <input type="hidden" name="existing_attachment" value="<?php echo h($editContribution['attachment']); ?>">

            <label>Kaun paisa de raha hai? (Member)</label>
            <select name="member_id" required>
                <option value="">-- Select --</option>
                <?php foreach ($members as $m): ?>
                    <option value="<?php echo $m['id']; ?>" <?php if ($m['id'] == $editContribution['member_id']) echo 'selected'; ?>>
                        <?php echo h($m['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Amount (₹)</label>
            <input type="number" name="amount" step="0.01" min="1" required value="<?php echo h($editContribution['amount']); ?>">

            <label>Date</label>
            <input type="date" name="date" value="<?php echo h($editContribution['date']); ?>">

            <label>Note</label>
            <textarea name="note" rows="2"><?php echo h($editContribution['note']); ?></textarea>

            <label>Bill / Screenshot (image)</label>
            <?php if (!empty($editContribution['attachment'])): ?>
                <div>
                    <a class="attachment-link" href="<?php echo h($editContribution['attachment']); ?>" target="_blank">Current file dekhne ke liye click karo</a>
                </div>
            <?php endif; ?>
            <input type="file" name="attachment" accept="image/*">

            <button class="btn" type="submit">Update Contribution</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($isAdmin && $editExpense): ?>
    <div class="card" id="edit_expense">
        <h2>Edit Expense (ID #<?php echo (int)$editExpense['id']; ?>)</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_expense">
            <input type="hidden" name="id" value="<?php echo (int)$editExpense['id']; ?>">
            <input type="hidden" name="existing_attachment" value="<?php echo h($editExpense['attachment']); ?>">

            <label>Amount Total (₹)</label>
            <input type="number" name="amount" step="0.01" min="1" required value="<?php echo h($editExpense['amount']); ?>">

            <label>Date</label>
            <input type="date" name="date" value="<?php echo h($editExpense['date']); ?>">

            <label>Kisne pay kiya? (For info only)</label>
            <select name="paid_by">
                <option value="">-- Select (optional) --</option>
                <?php foreach ($members as $m): ?>
                    <option value="<?php echo $m['id']; ?>" <?php if ($editExpense['paid_by'] == $m['id']) echo 'selected'; ?>>
                        <?php echo h($m['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Ye kharcha kin logon me batna hai? (select 3 / 4 / 5 / 6)</label>
            <div class="checkbox-group">
                <?php foreach ($members as $m): ?>
                    <label>
                        <input type="checkbox" name="participants[]" value="<?php echo $m['id']; ?>"
                            <?php if (in_array($m['id'], $editExpenseParticipants)) echo 'checked'; ?>>
                        <?php echo h($m['name']); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <small>Agar koi select nahi karoge to automatic sab 6 logon me divide hoga.</small>

            <label>Note</label>
            <textarea name="note" rows="2"><?php echo h($editExpense['note']); ?></textarea>

            <label>Bill / Screenshot (image)</label>
            <?php if (!empty($editExpense['attachment'])): ?>
                <div>
                    <a class="attachment-link" href="<?php echo h($editExpense['attachment']); ?>" target="_blank">Current file dekhne ke liye click karo</a>
                </div>
            <?php endif; ?>
            <input type="file" name="attachment" accept="image/*">

            <button class="btn" type="submit">Update Expense</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- MAIN FORMS -->
    <div class="section-grid">
        <div class="card">
            <h2>Common Account me Paisa Add</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_contribution">

                <label>Kaun paisa de raha hai? (Member)</label>
                <select name="member_id" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?php echo $m['id']; ?>"><?php echo h($m['name']); ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Amount (₹)</label>
                <input type="number" name="amount" step="0.01" min="1" required>

                <label>Date</label>
                <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>">

                <label>Note (optional)</label>
                <textarea name="note" rows="2" placeholder="Example: December 2025 monthly share"></textarea>

                <label>Bill / Screenshot (image)</label>
                <input type="file" name="attachment" accept="image/*">

                <button class="btn" type="submit">Add Contribution</button>
            </form>
        </div>

        <div class="card">
            <h2>Ghar ka Kharcha Add (Expense)</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_expense">

                <label>Amount Total (₹)</label>
                <input type="number" name="amount" step="0.01" min="1" required>

                <label>Date</label>
                <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>">

                <label>Kisne pay kiya? (For info only)</label>
                <select name="paid_by">
                    <option value="">-- Select (optional) --</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?php echo $m['id']; ?>"><?php echo h($m['name']); ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Ye kharcha kin logon me batna hai? (select 3 / 4 / 5 / 6)</label>
                <div class="checkbox-group">
                    <?php foreach ($members as $m): ?>
                        <label>
                            <input type="checkbox" name="participants[]" value="<?php echo $m['id']; ?>">
                            <?php echo h($m['name']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <small>Agar koi select nahi karoge to automatic sab 6 logon me divide hoga.</small>

                <label>Note (kya kharida, details, etc.)</label>
                <textarea name="note" rows="2" placeholder="Example: 900₹ grocery for all, 600₹ pizza only 5 log ke liye"></textarea>

                <label>Bill / Screenshot (image)</label>
                <input type="file" name="attachment" accept="image/*">

                <button class="btn" type="submit">Add Expense</button>
            </form>
        </div>
    </div>

    <!-- RECENT -->
    <div class="section-grid">
        <div class="card">
            <h2>Recent Contributions</h2>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Naam</th>
                    <th>Amount (₹)</th>
                    <th>Note</th>
                    <th>Bill</th>
                    <?php if ($isAdmin): ?><th>Action</th><?php endif; ?>
                </tr>
                <?php foreach ($recentContrib as $c): ?>
                    <tr>
                        <td><?php echo h($c['date']); ?></td>
                        <td><?php echo h($c['member_name']); ?></td>
                        <td><?php echo number_format($c['amount'], 2); ?></td>
                        <td><?php echo nl2br(h($c['note'])); ?></td>
                        <td>
                            <?php if (!empty($c['attachment'])): ?>
                                <a class="attachment-link" href="<?php echo h($c['attachment']); ?>" target="_blank">View bill</a>
                            <?php else: ?>
                                <small>-</small>
                            <?php endif; ?>
                        </td>
                        <?php if ($isAdmin): ?>
                        <td class="actions">
                            <a href="?edit_contribution=<?php echo (int)$c['id']; ?>#edit_contribution" class="btn btn-sm">Edit</a>
                            <a href="?delete_contribution=<?php echo (int)$c['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete contribution?');">Del</a>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="card">
            <h2>
                <span>Recent Expenses</span>
                <a href="?download_pdf=1" class="btn btn-sm">Download All PDF</a>
            </h2>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Amount (₹)</th>
                    <th>Split Info</th>
                    <th>Note</th>
                    <th>Bill</th>
                    <?php if ($isAdmin): ?><th>Action</th><?php endif; ?>
                </tr>
                <?php foreach ($recentExpenses as $e): ?>
                    <?php
                    $pstmt = $db->prepare("
                        SELECT ep.*, m.name AS member_name
                        FROM expense_participants ep
                        JOIN members m ON ep.member_id = m.id
                        WHERE ep.expense_id = ?
                    ");
                    $pstmt->execute([$e['id']]);
                    $parts = $pstmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <tr>
                        <td><?php echo h($e['date']); ?></td>
                        <td><?php echo number_format($e['amount'], 2); ?></td>
                        <td>
                            <?php if ($e['payer_name']): ?>
                                <span class="tag">Paid by: <?php echo h($e['payer_name']); ?></span><br>
                            <?php endif; ?>
                            <?php foreach ($parts as $p): ?>
                                <small><?php echo h($p['member_name']); ?>: <?php echo number_format($p['share'], 2); ?>₹</small><br>
                            <?php endforeach; ?>
                        </td>
                        <td><?php echo nl2br(h($e['note'])); ?></td>
                        <td>
                            <?php if (!empty($e['attachment'])): ?>
                                <a class="attachment-link" href="<?php echo h($e['attachment']); ?>" target="_blank">View bill</a>
                            <?php else: ?>
                                <small>-</small>
                            <?php endif; ?>
                        </td>
                        <?php if ($isAdmin): ?>
                        <td class="actions">
                            <a href="?edit_expense=<?php echo (int)$e['id']; ?>#edit_expense" class="btn btn-sm">Edit</a>
                            <a href="?delete_expense=<?php echo (int)$e['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete expense & its split?');">Del</a>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <!-- ELECTRICITY SECTION -->
    <div class="card">
        <h2>Electricity Bill (Flat) – 2 Meters</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_bill">

            <label>Month label</label>
            <input type="text" name="month_label" placeholder="Dec 2025" required>

            <div class="inline-row">
                <div>
                    <label>Start date</label>
                    <input type="date" name="start_date" value="<?php echo date('Y-m-01'); ?>">
                </div>
                <div>
                    <label>End date</label>
                    <input type="date" name="end_date" required value="<?php echo date('Y-m-t'); ?>">
                </div>
            </div>

            <div class="meter-box">
                <div class="meter-title">Direct Meter (Main supply)</div>
                <div class="inline-row">
                    <div>
                        <label>Previous reading</label>
                        <input type="number" step="0.01" name="direct_prev" placeholder="e.g. 40" value="<?php echo $lastBill ? h($lastBill['direct_curr']) : ''; ?>">
                    </div>
                    <div>
                        <label>Photo (Prev reading)</label>
                        <input type="file" name="direct_prev_photo" accept="image/*">
                    </div>
                </div>
                <div class="inline-row">
                    <div>
                        <label>Current reading</label>
                        <input type="number" step="0.01" name="direct_curr" placeholder="e.g. 80">
                    </div>
                    <div>
                        <label>Photo (Current reading)</label>
                        <input type="file" name="direct_curr_photo" accept="image/*">
                    </div>
                </div>
            </div>

            <div class="meter-box">
                <div class="meter-title">Inverter Meter</div>
                <div class="inline-row">
                    <div>
                        <label>Previous reading</label>
                        <input type="number" step="0.01" name="inverter_prev" placeholder="e.g. 20" value="<?php echo $lastBill ? h($lastBill['inverter_curr']) : ''; ?>">
                    </div>
                    <div>
                        <label>Photo (Prev reading)</label>
                        <input type="file" name="inverter_prev_photo" accept="image/*">
                    </div>
                </div>
                <div class="inline-row">
                    <div>
                        <label>Current reading</label>
                        <input type="number" step="0.01" name="inverter_curr" placeholder="e.g. 30">
                    </div>
                    <div>
                        <label>Photo (Current reading)</label>
                        <input type="file" name="inverter_curr_photo" accept="image/*">
                    </div>
                </div>
            </div>

            <div class="inline-row">
                <div>
                    <label>Per unit rate (₹)</label>
                    <input type="number" step="0.01" name="unit_rate" value="10">
                </div>
                <div>
                    <label>Note (optional)</label>
                    <input type="text" name="note" placeholder="Example: Dec flat bill">
                </div>
            </div>

            <button class="btn" type="submit">Calculate & Save Bill</button>
            <small style="display:block;margin-top:4px;">System automatically calculate karega: units = current - previous, fir total ₹ = (direct + inverter) × rate.</small>
        </form>
    </div>

    <!-- ELECTRIC BILLS LIST -->
    <div class="card">
        <h2>Electricity Bills History</h2>
        <?php if (empty($electricBills)): ?>
            <small class="muted">Abhi tak koi bill save nahi hua.</small>
        <?php else: ?>
            <table>
                <tr>
                    <th>Month</th>
                    <th>Period</th>
                    <th>Units (D / I)</th>
                    <th>Rate & Amount</th>
                    <th>Photos</th>
                    <th>PDF</th>
                    <?php if ($isAdmin): ?><th>Action</th><?php endif; ?>
                </tr>
                <?php foreach ($electricBills as $b): ?>
                    <tr>
                        <td><?php echo h($b['month_label']); ?></td>
                        <td><?php echo h($b['start_date']); ?> → <?php echo h($b['end_date']); ?></td>
                        <td>
                            <small>D: <?php echo $b['direct_units']; ?>, I: <?php echo $b['inverter_units']; ?></small>
                        </td>
                        <td>
                            <!-- <small>Rate: <?php echo $b['unit_rate']; ?>/unit<br>Total: <?php echo $b['total_amount']; ?> ₹</small> -->

                            <small>
                                Rate: <?php echo $b['unit_rate']; ?>/unit<br>
                                Direct: <?php echo ($b['direct_units'] * $b['unit_rate']); ?> ₹<br>
                                Inverter (Your 50%): <?php echo (($b['inverter_units'] * $b['unit_rate']) / 2); ?> ₹<br>
                                Inverter (Owner 50%): <?php echo (($b['inverter_units'] * $b['unit_rate']) / 2); ?> ₹<br>
                                <strong>Total (You Pay): <?php echo $b['total_amount']; ?> ₹</strong>
                            </small>


                        </td>
                        <td>
                            <?php
                            $hasPhoto = false;
                            if (!empty($b['direct_prev_photo'])) { $hasPhoto = true; ?>
                                <a class="attachment-link" href="<?php echo h($b['direct_prev_photo']); ?>" target="_blank">D Prev</a><br>
                            <?php }
                            if (!empty($b['direct_curr_photo'])) { $hasPhoto = true; ?>
                                <a class="attachment-link" href="<?php echo h($b['direct_curr_photo']); ?>" target="_blank">D Curr</a><br>
                            <?php }
                            if (!empty($b['inverter_prev_photo'])) { $hasPhoto = true; ?>
                                <a class="attachment-link" href="<?php echo h($b['inverter_prev_photo']); ?>" target="_blank">I Prev</a><br>
                            <?php }
                            if (!empty($b['inverter_curr_photo'])) { $hasPhoto = true; ?>
                                <a class="attachment-link" href="<?php echo h($b['inverter_curr_photo']); ?>" target="_blank">I Curr</a>
                            <?php }
                            if (!$hasPhoto): ?>
                                <small>-</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="btn btn-sm" href="?bill_pdf=<?php echo (int)$b['id']; ?>">Download</a>
                        </td>
                        <?php if ($isAdmin): ?>
                        <td class="actions">
                            <a href="?edit_bill=<?php echo (int)$b['id']; ?>#edit_bill" class="btn btn-sm">Edit</a>
                            <a href="?delete_bill=<?php echo (int)$b['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this bill?');">Del</a>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <?php if ($isAdmin && $editBill): ?>
    <div class="card" id="edit_bill">
        <h2>Edit Electricity Bill (<?php echo h($editBill['month_label']); ?>)</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_bill">
            <input type="hidden" name="id" value="<?php echo (int)$editBill['id']; ?>">
            <input type="hidden" name="existing_dp" value="<?php echo h($editBill['direct_prev_photo']); ?>">
            <input type="hidden" name="existing_dc" value="<?php echo h($editBill['direct_curr_photo']); ?>">
            <input type="hidden" name="existing_ip" value="<?php echo h($editBill['inverter_prev_photo']); ?>">
            <input type="hidden" name="existing_ic" value="<?php echo h($editBill['inverter_curr_photo']); ?>">

            <label>Month label</label>
            <input type="text" name="month_label" required value="<?php echo h($editBill['month_label']); ?>">

            <div class="inline-row">
                <div>
                    <label>Start date</label>
                    <input type="date" name="start_date" value="<?php echo h($editBill['start_date']); ?>">
                </div>
                <div>
                    <label>End date</label>
                    <input type="date" name="end_date" required value="<?php echo h($editBill['end_date']); ?>">
                </div>
            </div>

            <div class="meter-box">
                <div class="meter-title">Direct Meter</div>
                <div class="inline-row">
                    <div>
                        <label>Previous reading</label>
                        <input type="number" step="0.01" name="direct_prev" value="<?php echo h($editBill['direct_prev']); ?>">
                    </div>
                    <div>
                        <label>Photo (Prev reading)</label>
                        <?php if (!empty($editBill['direct_prev_photo'])): ?>
                            <a class="attachment-link" href="<?php echo h($editBill['direct_prev_photo']); ?>" target="_blank">Current</a><br>
                        <?php endif; ?>
                        <input type="file" name="direct_prev_photo" accept="image/*">
                    </div>
                </div>
                <div class="inline-row">
                    <div>
                        <label>Current reading</label>
                        <input type="number" step="0.01" name="direct_curr" value="<?php echo h($editBill['direct_curr']); ?>">
                    </div>
                    <div>
                        <label>Photo (Current reading)</label>
                        <?php if (!empty($editBill['direct_curr_photo'])): ?>
                            <a class="attachment-link" href="<?php echo h($editBill['direct_curr_photo']); ?>" target="_blank">Current</a><br>
                        <?php endif; ?>
                        <input type="file" name="direct_curr_photo" accept="image/*">
                    </div>
                </div>
            </div>

            <div class="meter-box">
                <div class="meter-title">Inverter Meter</div>
                <div class="inline-row">
                    <div>
                        <label>Previous reading</label>
                        <input type="number" step="0.01" name="inverter_prev" value="<?php echo h($editBill['inverter_prev']); ?>">
                    </div>
                    <div>
                        <label>Photo (Prev reading)</label>
                        <?php if (!empty($editBill['inverter_prev_photo'])): ?>
                            <a class="attachment-link" href="<?php echo h($editBill['inverter_prev_photo']); ?>" target="_blank">Current</a><br>
                        <?php endif; ?>
                        <input type="file" name="inverter_prev_photo" accept="image/*">
                    </div>
                </div>
                <div class="inline-row">
                    <div>
                        <label>Current reading</label>
                        <input type="number" step="0.01" name="inverter_curr" value="<?php echo h($editBill['inverter_curr']); ?>">
                    </div>
                    <div>
                        <label>Photo (Current reading)</label>
                        <?php if (!empty($editBill['inverter_curr_photo'])): ?>
                            <a class="attachment-link" href="<?php echo h($editBill['inverter_curr_photo']); ?>" target="_blank">Current</a><br>
                        <?php endif; ?>
                        <input type="file" name="inverter_curr_photo" accept="image/*">
                    </div>
                </div>
            </div>

            <div class="inline-row">
                <div>
                    <label>Per unit rate (₹)</label>
                    <input type="number" step="0.01" name="unit_rate" value="<?php echo h($editBill['unit_rate']); ?>">
                </div>
                <div>
                    <label>Note</label>
                    <input type="text" name="note" value="<?php echo h($editBill['note']); ?>">
                </div>
            </div>

            <button class="btn" type="submit">Update Bill</button>
        </form>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
