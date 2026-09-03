<?php
/**
 * Hospitals & Availability section component.
 */


/*
|--------------------------------------------------------------------------
| Self-working fallbacks
|--------------------------------------------------------------------------
| This component can live inside admin/doctors/hospitals-availability.php.
| The main doctor form only includes/renders it. The component safely handles
| its own DB checks, chamber table boot, AJAX location/hospital search,
| add/update/delete chamber logic, CSS and JS.
*/
if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('doctor_hospitals_availability_has_pdo')) {
    function doctor_hospitals_availability_has_pdo(): bool
    {
        global $pdo;
        return isset($pdo) && $pdo instanceof PDO;
    }
}

if (!function_exists('doctor_hospitals_availability_safe_identifier')) {
    function doctor_hospitals_availability_safe_identifier(string $name): string
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $name) ? $name : '';
    }
}

if (!function_exists('doctor_hospitals_availability_json')) {
    function doctor_hospitals_availability_json(array $payload): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('doctor_component_table_exists')) {
    function doctor_component_table_exists(string $table): bool
    {
        global $pdo;

        if (!doctor_hospitals_availability_has_pdo() || doctor_hospitals_availability_safe_identifier($table) === '') {
            return false;
        }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table");
            $stmt->execute([':table' => $table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('doctor_component_column_exists')) {
    function doctor_component_column_exists(string $table, string $column): bool
    {
        global $pdo;

        if (
            !doctor_hospitals_availability_has_pdo() ||
            doctor_hospitals_availability_safe_identifier($table) === '' ||
            doctor_hospitals_availability_safe_identifier($column) === ''
        ) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column");
            $stmt->execute([':table' => $table, ':column' => $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('doctor_component_add_column_if_missing')) {
    function doctor_component_add_column_if_missing(string $table, string $column, string $definition): void
    {
        global $pdo;

        if (!doctor_component_table_exists($table)) {
            return;
        }

        if (!doctor_component_column_exists($table, $column)) {
            $safe_table = doctor_hospitals_availability_safe_identifier($table);
            $safe_column = doctor_hospitals_availability_safe_identifier($column);

            if ($safe_table === '' || $safe_column === '' || !doctor_hospitals_availability_has_pdo()) {
                return;
            }

            try {
                $pdo->exec("ALTER TABLE `{$safe_table}` ADD COLUMN `{$safe_column}` {$definition}");
            } catch (Throwable $e) {
                // Column boot should not break page rendering.
            }
        }
    }
}

if (!function_exists('doctor_hospitals_availability_hospital_url')) {
    function doctor_hospitals_availability_hospital_url(array $hospital): string
    {
        $id = (int)($hospital['hospital_id'] ?? $hospital['id'] ?? 0);
        $slug = trim((string)($hospital['hospital_slug'] ?? $hospital['slug'] ?? ''));

        if ($slug !== '') {
            return '../hospital/' . rawurlencode($slug);
        }

        if ($id > 0) {
            return 'hospitals.php?id=' . $id;
        }

        return 'hospitals.php';
    }
}


if (!function_exists('doctor_hospitals_availability_hospital_edit_url')) {
    function doctor_hospitals_availability_hospital_edit_url(array $hospital): string
    {
        $id = (int)($hospital['hospital_id'] ?? $hospital['id'] ?? 0);

        if ($id > 0) {
            return '/admin/hospital-form.php?id=' . $id;
        }

        return '/admin/hospital-form.php';
    }
}

if (!function_exists('doctor_hospitals_availability_css')) {
    function doctor_hospitals_availability_css(): void
    {
        static $printed = false;

        if ($printed) {
            return;
        }

        $printed = true;
        ?>
        <style>
          :root{
            --gh-bg:#f6f8fa;
            --gh-card:#ffffff;
            --gh-text:#24292f;
            --gh-muted:#57606a;
            --gh-border:#d0d7de;
            --gh-soft:#f6f8fa;
            --gh-blue:#0969da;
            --gh-blue-soft:#ddf4ff;
            --gh-green:#1a7f37;
            --gh-red:#cf222e;
            --gh-red-soft:#ffebe9;
          }

          .lite-section{position:relative;padding:24px;border-radius:12px;background:var(--gh-card);border:1px solid var(--gh-border);margin-bottom:20px;box-shadow:0 1px 0 rgba(27,31,36,.04)}
          .lite-section-title{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px;padding-bottom:14px;border-bottom:1px dashed #d8dee4}
          .lite-section-title h3{position:relative;margin:0;padding-left:14px;color:var(--gh-text);font-size:17px;letter-spacing:-.02em}
          .lite-section-title h3:before{content:"";position:absolute;left:0;top:3px;width:5px;height:18px;border-radius:99px;background:var(--gh-blue)}
          .lite-section-title span{color:var(--gh-muted);font-size:13px;line-height:1.5;text-align:right}

          .wp-style-chamber-box,.lite-chamber-card,.lite-auto-box,.wp-preview-note{border-radius:12px;background:#fff;border:1px solid var(--gh-border);box-shadow:0 1px 0 rgba(27,31,36,.04)}
          .wp-style-chamber-head{padding:14px 16px;border-bottom:1px solid var(--gh-border);background:var(--gh-soft);color:var(--gh-text);font-size:14px;font-weight:700!important}
          .wp-style-chamber-body{padding:16px}
          .wp-chain-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr)) auto;gap:11px;align-items:end}
          .wp-chain-grid label,.wp-schedule-grid label{display:block;margin-bottom:6px;color:#344054;font-size:13px;font-weight:600!important}

          .wp-chain-grid input,
          .wp-chain-grid select,
          .wp-schedule-grid input,
          .wp-schedule-grid select{
            width:100%;
            min-height:42px;
            border:1px solid var(--gh-border);
            border-radius:6px;
            padding:10px 12px;
            color:var(--gh-text);
            background:#fff;
            outline:none;
            font-family:inherit;
            font-size:14px;
          }

          .wp-chain-grid input:focus,
          .wp-chain-grid select:focus,
          .wp-schedule-grid input:focus,
          .wp-schedule-grid select:focus{
            border-color:var(--gh-blue);
            box-shadow:0 0 0 3px rgba(9,105,218,.12);
          }

          .wp-schedule-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:14px}
          .wp-field-pair{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;grid-column:1 / -1}
          .wp-field-pair > div{min-width:0}
          .lite-chamber-list{display:grid;gap:14px;margin-top:16px}
          .lite-chamber-table-wrap{
            width:100%;
            overflow-x:auto;
            margin-top:16px;
            border:1px solid var(--gh-border);
            border-radius:12px;
            background:#fff;
          }

          .lite-chamber-table{
            width:100%;
            border-collapse:separate;
            border-spacing:0;
            min-width:760px;
            color:var(--gh-text);
          }

          .lite-chamber-table th{
            padding:13px 14px;
            background:#f6f8fa;
            border-bottom:1px solid var(--gh-border);
            color:#57606a;
            font-size:12px;
            line-height:1.35;
            text-align:left;
            font-weight:800!important;
            white-space:nowrap;
          }

          .lite-chamber-table td{
            padding:14px;
            border-bottom:1px solid #d8dee4;
            vertical-align:middle;
            font-size:13.5px;
            line-height:1.45;
          }

          .lite-chamber-table tbody tr:last-child td{border-bottom:0}
          .lite-chamber-table .lite-chamber-edit-row td{background:#f6f8fa;padding:16px}
          .lite-chamber-table .lite-chamber-edit-row{display:none}
          .lite-chamber-table .lite-chamber-edit-row.is-open{display:table-row}
          .lite-chamber-entry.is-editing [data-chamber-edit-toggle]{visibility:hidden!important;pointer-events:none!important;display:inline-flex!important}

          .lite-chamber-name-cell{display:grid;gap:4px;min-width:220px}
          .lite-chamber-name-main{display:flex;align-items:center;gap:10px;min-width:0}
          .lite-chamber-name-main strong{font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
          .lite-chamber-sub{color:var(--gh-muted);font-size:12px;line-height:1.45;max-width:320px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
          .lite-serial-cell{width:70px;text-align:center;color:#24292f;font-size:14px;font-weight:800!important;}
          .lite-serial-number{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:6px;border:1px solid #d0d7de;background:#f6f8fa;color:#24292f;font-weight:800!important;}

          .lite-table-action-group{display:inline-flex;align-items:center;justify-content:flex-start;gap:8px;white-space:nowrap;min-width:104px;}
          .lite-table-btn{
            display:inline-flex!important;
            align-items:center!important;
            justify-content:center!important;
            min-height:34px!important;
            padding:7px 13px!important;
            border:1px solid rgba(27,31,36,.15)!important;
            border-radius:6px!important;
            background:#f6f8fa!important;
            color:#24292f!important;
            font-family:inherit!important;
            font-size:13px!important;
            line-height:1!important;
            font-weight:700!important;
            text-decoration:none!important;
            cursor:pointer!important;
            box-shadow:none!important;
          }
          .lite-table-btn:hover{background:#eef1f4!important;color:#24292f!important}
          .lite-table-btn-delete{background:#f6f8fa!important;color:#cf222e!important;border-color:rgba(27,31,36,.15)!important}
          .lite-table-btn-delete:hover{background:#ffebe9!important;color:#cf222e!important;border-color:#ff818266!important}


          .lite-chamber-entry.is-highlighted {
            animation: lite-chamber-row-highlight 1.6s ease;
          }

          @keyframes lite-chamber-row-highlight {
            0% { background:#fff8c5; }
            100% { background:#fff; }
          }

          .lite-table-action-group .lite-chamber-menu-wrap{position:relative;}
          .lite-table-action-group .lite-chamber-menu{top:32px;right:0;min-width:170px;}
          .lite-table-action-group .lite-kebab{color:#57606a!important;background:transparent!important;}
          .lite-table-action-group .lite-kebab:hover{color:#57606a!important;background:transparent!important;}
          .lite-chamber-empty-cell{padding:16px!important;color:var(--gh-muted)!important;font-size:13px!important}

          .lite-chamber-card{padding:18px}
          .lite-chamber-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:0;padding-bottom:0;border-bottom:0;cursor:pointer}
          .lite-chamber-head h4{margin:0 0 5px;color:var(--gh-text);font-size:16px}
          .lite-chamber-head p{margin:0;color:var(--gh-muted);font-size:13px;line-height:1.6}
          .lite-chamber-options{display:none;margin-top:16px;padding-top:16px;border-top:1px dashed #d8dee4}
          .lite-chamber-card.is-open .lite-chamber-options{display:block}
          .lite-chamber-title-row{
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
            margin-bottom:6px;
          }

          .lite-chamber-title-row h4{
            margin:0;
          }

          .lite-chamber-badge{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            width:28px;
            height:28px;
            border-radius:6px;
            font-size:14px;
            line-height:1;
            font-weight:800!important;
            border:1px solid #d0d7de;
            background:#f6f8fa;
            color:#24292f;
            white-space:nowrap;
          }

          .chamber-color-1{background:#ddf4ff;color:#0969da;border-color:#54aeff66}
          .chamber-color-2{background:#dafbe1;color:#1a7f37;border-color:#2da44e66}
          .chamber-color-3{background:#fbefff;color:#8250df;border-color:#d8b9ff}
          .chamber-color-4{background:#fff8c5;color:#7d4e00;border-color:#f0d98c}
          .chamber-color-5{background:#ffebe9;color:#cf222e;border-color:#ff818266}
          .chamber-color-6{background:#ddf4ff;color:#0550ae;border-color:#54aeff66}
          .chamber-color-7{background:#dafbe1;color:#116329;border-color:#2da44e66}
          .chamber-color-8{background:#f6f8fa;color:#24292f;border-color:#d0d7de}
          .chamber-color-9{background:#fbefff;color:#6639ba;border-color:#d8b9ff}

          .lite-card-actions{
            position:relative;
            display:flex;
            align-items:flex-start;
            justify-content:flex-end;
            flex:0 0 auto;
          }

          .lite-card-actions form{margin:0;display:block}

          .lite-chamber-menu-wrap{
            position:relative;
            display:inline-flex;
            align-items:center;
            justify-content:center;
          }

          .lite-kebab{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            width:26px;
            height:26px;
            border:0;
            background:transparent;
            color:#57606a;
            padding:0;
            margin:0;
            cursor:pointer;
            font-size:16px;
            line-height:1;
            box-shadow:none;
          }

          .lite-kebab:hover,
          .lite-kebab:focus,
          .lite-kebab:active,
          .lite-chamber-menu-wrap.is-open .lite-kebab{
            color:#57606a !important;
            background:transparent !important;
            border-radius:0 !important;
            box-shadow:none !important;
            transform:none !important;
          }

          .lite-kebab i{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            width:16px;
            height:16px;
            font-size:16px;
            line-height:1;
          }

          .lite-chamber-menu{
            position:absolute;
            top:30px;
            right:0;
            z-index:100;
            display:none;
            min-width:158px;
            padding:6px 0;
            border:1px solid #d0d7de;
            border-radius:6px;
            background:#fff;
            box-shadow:0 8px 24px rgba(140,149,159,.24);
          }

          .lite-chamber-menu-wrap.is-open .lite-chamber-menu{
            display:block;
          }

          .lite-chamber-menu form{
            display:block !important;
            width:100% !important;
            margin:0 !important;
            padding:0 !important;
          }

          .lite-menu-link,
          .lite-menu-button{
            display:flex !important;
            align-items:center !important;
            justify-content:flex-start !important;
            gap:11px !important;
            width:100% !important;
            min-height:34px !important;
            padding:8px 12px !important;
            border:0 !important;
            border-radius:0 !important;
            background:transparent !important;
            color:#57606a !important;
            cursor:pointer !important;
            font-family:inherit !important;
            font-size:12.5px !important;
            line-height:1.35 !important;
            text-align:left !important;
            text-decoration:none !important;
            white-space:nowrap !important;
            font-weight:600!important;
            box-shadow:none !important;
            transform:none !important;
          }

          .lite-menu-link:hover,
          .lite-menu-button:hover{
            background:#f6f8fa !important;
            color:#57606a !important;
            text-decoration:none !important;
            box-shadow:none !important;
            transform:none !important;
          }

          .lite-menu-icon{
            width:15px;
            min-width:15px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            color:#6a737d;
            font-size:13px;
            line-height:1;
          }

          .lite-menu-link:hover .lite-menu-icon,
          .lite-menu-button:hover .lite-menu-icon{
            color:inherit;
          }

          .lite-menu-danger,
          .lite-menu-danger:hover,
          .lite-menu-danger:focus,
          .lite-menu-danger:active{
            color:#57606a !important;
            background:transparent !important;
            border:0 !important;
            box-shadow:none !important;
          }

          .lite-menu-danger:hover{
            background:#f6f8fa !important;
          }

          .lite-menu-danger .lite-menu-icon{
            color:#6a737d !important;
          }

          .lite-mini-btn,.wp-admin-button,.lite-btn{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:7px;
            min-height:34px;
            padding:7px 11px;
            border-radius:7px;
            border:1px solid rgba(27,31,36,.15);
            cursor:pointer;
            font-family:inherit;
            font-size:12.5px;
            line-height:1;
            text-decoration:none;
            white-space:nowrap;
            font-weight:700!important;
            transition:background .12s ease,border-color .12s ease,box-shadow .12s ease,transform .12s ease;
          }

          .lite-mini-btn:hover,
          .lite-btn:hover,
          .wp-admin-button:hover{
            transform:translateY(-1px);
            box-shadow:0 2px 6px rgba(27,31,36,.08);
          }

          .wp-admin-button,.lite-btn-primary{background:#2da44e;color:#fff}
          .wp-admin-button:hover,.lite-btn-primary:hover{background:#1f883d;color:#fff}
          .lite-mini-btn{color:var(--gh-text);background:#fff}
          .lite-mini-btn:hover{background:#f3f4f6;color:var(--gh-text)}
          .lite-danger-btn{color:#cf222e;background:#fff;border-color:#ff818266}
          .lite-danger-btn:hover{background:#ffebe9;color:#cf222e;border-color:#ff8182}
          .lite-view-btn{color:#0969da;background:#fff;border-color:#54aeff66}
          .lite-view-btn:hover{background:#ddf4ff;color:#0969da;border-color:#54aeff}
          .lite-edit-btn{color:#8250df;background:#fff;border-color:#d8b9ff}
          .lite-edit-btn:hover{background:#fbefff;color:#6639ba;border-color:#d8b9ff}
          .lite-status-badge{display:inline-flex;align-items:center;min-height:26px;padding:4px 9px;border-radius:999px;font-size:12px;font-weight:700!important;border:1px solid transparent}
          .lite-status-active{color:var(--gh-green);background:#dafbe1;border-color:#2da44e66}
          .lite-status-inactive{color:var(--gh-red);background:var(--gh-red-soft);border-color:#ff818266}
          .lite-edit-form-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:14px}
          .lite-btn-light{background:#fff!important;color:var(--gh-text)!important;border:1px solid var(--gh-border)!important}
          .lite-btn-light:hover{background:#f6f8fa!important;color:var(--gh-text)!important}

          .lite-auto-box,.wp-preview-note{padding:12px 14px;color:var(--gh-muted);font-size:12.5px;line-height:1.6}
          .wp-preview-note{margin-top:12px}

          .wp-days-row{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
          .wp-day-pill{
            position:relative;
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:8px 11px;
            border:1px solid var(--gh-border);
            border-radius:999px;
            background:#fff;
            color:#344054;
            font-size:12px;
            cursor:pointer;
            user-select:none;
            transition:.12s ease;
          }
          .wp-day-pill:hover{border-color:#8c959f;background:#f6f8fa}
          .wp-day-pill input{position:absolute;opacity:0;pointer-events:none}
          .wp-day-check{
            width:16px;
            height:16px;
            border:1px solid #8c959f;
            border-radius:4px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            background:#fff;
            flex:0 0 auto;
          }
          .wp-day-check:after{
            content:"";
            width:8px;
            height:4px;
            border-left:2px solid #fff;
            border-bottom:2px solid #fff;
            transform:rotate(-45deg);
            margin-top:-2px;
            opacity:0;
          }
          .wp-day-pill input:checked + .wp-day-check{background:var(--gh-green);border-color:var(--gh-green)}
          .wp-day-pill input:checked + .wp-day-check:after{opacity:1}
          .wp-day-pill input:checked ~ .wp-day-text{color:var(--gh-green);font-weight:700!important}
          .wp-day-pill:has(input:checked){border-color:#2da44e66;background:#dafbe1}
          .wp-day-pill-closed input:checked + .wp-day-check{background:var(--gh-red);border-color:var(--gh-red)}
          .wp-day-pill-closed input:checked ~ .wp-day-text{color:var(--gh-red)}
          .wp-day-pill-closed:has(input:checked){border-color:#ff818266;background:var(--gh-red-soft)}

          .wp-search-dropdown{position:relative}
          .wp-search-dropdown-input{
            width:100%;
            min-height:42px;
            border:1px solid var(--gh-border);
            border-radius:6px;
            padding:10px 38px 10px 12px;
            color:var(--gh-text);
            background:#fff;
            outline:none;
            font-family:inherit;
            font-size:14px;
            cursor:text;
          }
          .wp-search-dropdown-input:focus{border-color:var(--gh-blue);box-shadow:0 0 0 3px rgba(9,105,218,.12)}
          .wp-search-dropdown-arrow{position:absolute;right:10px;top:10px;width:22px;height:22px;display:flex;align-items:center;justify-content:center;color:var(--gh-muted);pointer-events:none;font-size:12px}
          .wp-search-dropdown-list{
            position:absolute;
            left:0;
            right:0;
            top:calc(100% + 6px);
            z-index:80;
            display:none;
            max-height:280px;
            overflow:auto;
            border:1px solid var(--gh-border);
            border-radius:8px;
            background:#fff;
            box-shadow:0 8px 24px rgba(140,149,159,.25);
          }
          .wp-search-dropdown.is-open .wp-search-dropdown-list{display:block}
          .wp-search-dropdown-item{padding:10px 12px;cursor:pointer;color:var(--gh-text);font-size:14px;line-height:1.4;border-bottom:1px solid #f0f2f4}
          .wp-search-dropdown-item:hover,.wp-search-dropdown-item.is-active{background:var(--gh-soft);color:var(--gh-blue)}
          .wp-search-dropdown-empty,.wp-search-dropdown-loading{padding:13px;color:var(--gh-muted);font-size:13px;text-align:center}
          .wp-search-dropdown-meta{display:block;margin-top:3px;color:var(--gh-muted);font-size:11.5px}

          .lite-chamber-summary{
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:8px 12px;
            margin-top:12px;
            padding:12px;
            border:1px solid #d8dee4;
            border-radius:10px;
            background:#f6f8fa;
          }
          .lite-chamber-summary-item{
            display:flex;
            gap:7px;
            align-items:flex-start;
            min-width:0;
            color:var(--gh-muted);
            font-size:12.5px;
            line-height:1.5;
          }
          .lite-chamber-summary-item.full{grid-column:1 / -1}
          .lite-chamber-summary-label{
            flex:0 0 auto;
            color:var(--gh-text);
            font-weight:700!important;
          }
          .lite-chamber-summary-value{
            min-width:0;
            overflow-wrap:anywhere;
          }
          .lite-required-note{
            margin-top:10px;
            color:var(--gh-muted);
            font-size:12px;
            line-height:1.5;
          }

          @media(max-width:900px){
            .wp-chain-grid,.wp-schedule-grid,.lite-chamber-summary{grid-template-columns:1fr}
            .lite-section-title,.lite-chamber-head{align-items:stretch;flex-direction:column}
            .lite-section-title span{text-align:left}
            .lite-card-actions{justify-content:flex-start;width:100%}
          }
        </style>
        <?php
    }
}

if (!function_exists('doctor_hospitals_availability_ensure_table')) {
    function doctor_hospitals_availability_ensure_table(): void
    {
        global $pdo;

        if (!doctor_component_table_exists('chambers')) {
            $pdo->exec("CREATE TABLE chambers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                doctor_id INT NOT NULL DEFAULT 0,
                hospital_id INT NOT NULL DEFAULT 0,
                address TEXT NULL,
                address_bn TEXT NULL,
                visiting_hours VARCHAR(255) NULL,
                visiting_hours_bn VARCHAR(255) NULL,
                consultation_fee DECIMAL(10,2) DEFAULT 0,
                appointment_phone VARCHAR(80) NULL,
                schedule VARCHAR(255) NULL,
                schedule_bn VARCHAR(255) NULL,
                available_days TEXT NULL,
                available_from VARCHAR(20) NULL,
                available_to VARCHAR(20) NULL,
                is_closed TINYINT(1) DEFAULT 0,
                status VARCHAR(20) DEFAULT 'active',
                sort_order INT DEFAULT 0,
                created_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        foreach ([
            'doctor_id' => "INT NOT NULL DEFAULT 0",
            'hospital_id' => "INT NOT NULL DEFAULT 0",
            'address' => "TEXT NULL",
            'address_bn' => "TEXT NULL",
            'visiting_hours' => "VARCHAR(255) NULL",
            'visiting_hours_bn' => "VARCHAR(255) NULL",
            'consultation_fee' => "DECIMAL(10,2) DEFAULT 0",
            'appointment_phone' => "VARCHAR(80) NULL",
            'schedule' => "VARCHAR(255) NULL",
            'schedule_bn' => "VARCHAR(255) NULL",
            'available_days' => "TEXT NULL",
            'available_from' => "VARCHAR(20) NULL",
            'available_to' => "VARCHAR(20) NULL",
            'is_closed' => "TINYINT(1) DEFAULT 0",
            'status' => "VARCHAR(20) DEFAULT 'active'",
            'sort_order' => "INT DEFAULT 0",
            'created_at' => "DATETIME NULL",
        ] as $column => $definition) {
            doctor_component_add_column_if_missing('chambers', $column, $definition);
        }
    }
}

if (!function_exists('doctor_hospitals_availability_get_divisions')) {
    function doctor_hospitals_availability_get_divisions(): array
    {
        global $pdo;

        if (!doctor_component_table_exists('divisions')) {
            return [];
        }

        try {
            return $pdo->query("SELECT id, name_en AS name FROM divisions ORDER BY name_en ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('doctor_hospitals_availability_get_chambers')) {
    function doctor_hospitals_availability_get_chambers(int $doctor_id): array
    {
        global $pdo;

        if ($doctor_id <= 0 || !doctor_component_table_exists('chambers')) {
            return [];
        }

        $hospital_name_select = doctor_component_column_exists('hospitals', 'name') ? 'h.name AS hospital_name,' : "CONCAT('Hospital #', h.id) AS hospital_name,";
        $slug_select = doctor_component_column_exists('hospitals', 'slug') ? 'h.slug AS hospital_slug,' : "'' AS hospital_slug,";
        $address_select = doctor_component_column_exists('hospitals', 'address') ? 'h.address AS hospital_address,' : "'' AS hospital_address,";
        $phone_select = doctor_component_column_exists('hospitals', 'phone') ? 'h.phone AS hospital_phone,' : "'' AS hospital_phone,";

        $location_joins = '';
        $district_select = "'' AS hospital_district_name,";
        $thana_select = "'' AS hospital_thana_name,";

        if (doctor_component_column_exists('hospitals', 'district_id') && doctor_component_table_exists('districts')) {
            $location_joins .= ' LEFT JOIN districts d ON d.id = h.district_id';

            if (doctor_component_column_exists('districts', 'name_en')) {
                $district_select = "COALESCE(d.name_en, '') AS hospital_district_name,";
            } elseif (doctor_component_column_exists('districts', 'name')) {
                $district_select = "COALESCE(d.name, '') AS hospital_district_name,";
            }
        }

        if (doctor_component_column_exists('hospitals', 'thana_id') && doctor_component_table_exists('thanas')) {
            $location_joins .= ' LEFT JOIN thanas t ON t.id = h.thana_id';

            if (doctor_component_column_exists('thanas', 'name_en')) {
                $thana_select = "COALESCE(t.name_en, '') AS hospital_thana_name,";
            } elseif (doctor_component_column_exists('thanas', 'name')) {
                $thana_select = "COALESCE(t.name, '') AS hospital_thana_name,";
            }
        }

        try {
            $stmt = $pdo->prepare("
                SELECT c.*, {$hospital_name_select} {$slug_select} {$address_select} {$phone_select} {$district_select} {$thana_select} h.id AS hospital_id_real
                FROM chambers c
                LEFT JOIN hospitals h ON h.id = c.hospital_id
                {$location_joins}
                WHERE c.doctor_id = :doctor_id
                ORDER BY c.sort_order ASC, c.id ASC
            ");
            $stmt->execute([':doctor_id' => $doctor_id]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('doctor_hospitals_availability_clean_days')) {
    function doctor_hospitals_availability_clean_days(array $days): string
    {
        $allowed = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'];
        $clean = [];

        foreach ($days as $day) {
            $day = trim((string)$day);
            if (in_array($day, $allowed, true)) {
                $clean[] = $day;
            }
        }

        return implode(',', array_values(array_unique($clean)));
    }
}

if (!defined('DOCTOR_FORM_MAIN_CONTEXT') && !function_exists('doctor_form_clean_days')) {
    function doctor_form_clean_days(array $days): string
    {
        return doctor_hospitals_availability_clean_days($days);
    }
}

if (!function_exists('doctor_hospitals_availability_selected_days')) {
    function doctor_hospitals_availability_selected_days(string $days): array
    {
        return trim($days) === ''
            ? []
            : array_filter(array_map('trim', explode(',', $days)));
    }
}

if (!defined('DOCTOR_FORM_MAIN_CONTEXT') && !function_exists('doctor_form_selected_days')) {
    function doctor_form_selected_days(string $days): array
    {
        return doctor_hospitals_availability_selected_days($days);
    }
}

if (!function_exists('doctor_hospitals_availability_day_labels')) {
    function doctor_hospitals_availability_day_labels(): array
    {
        return [
            'sat' => 'Saturday',
            'sun' => 'Sunday',
            'mon' => 'Monday',
            'tue' => 'Tuesday',
            'wed' => 'Wednesday',
            'thu' => 'Thursday',
            'fri' => 'Friday',
        ];
    }
}

if (!function_exists('doctor_hospitals_availability_format_days')) {
    function doctor_hospitals_availability_format_days(string $days): string
    {
        $selected = doctor_hospitals_availability_selected_days($days);

        if (empty($selected)) {
            return '';
        }

        $labels = doctor_hospitals_availability_day_labels();
        $clean = [];

        foreach ($selected as $day) {
            if (isset($labels[$day])) {
                $clean[] = $labels[$day];
            }
        }

        return implode(', ', $clean);
    }
}

if (!function_exists('doctor_hospitals_availability_format_single_time')) {
    function doctor_hospitals_availability_format_single_time($time): string
    {
        $time = trim((string)$time);

        if ($time === '') {
            return '';
        }

        $timestamp = strtotime($time);

        if ($timestamp === false) {
            return $time;
        }

        return date('h:i A', $timestamp);
    }
}

if (!function_exists('doctor_hospitals_availability_format_time_range')) {
    function doctor_hospitals_availability_format_time_range($from, $to): string
    {
        $from = doctor_hospitals_availability_format_single_time($from);
        $to = doctor_hospitals_availability_format_single_time($to);

        if ($from !== '' && $to !== '') {
            return $from . ' - ' . $to;
        }

        return $from !== '' ? $from : $to;
    }
}

if (!function_exists('doctor_hospitals_availability_get_hospital_name')) {
    function doctor_hospitals_availability_get_hospital_name(int $hospital_id): string
    {
        global $pdo;

        if ($hospital_id <= 0 || !doctor_component_table_exists('hospitals') || !doctor_component_column_exists('hospitals', 'name')) {
            return '';
        }

        try {
            $stmt = $pdo->prepare("SELECT name FROM hospitals WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $hospital_id]);
            return trim((string)$stmt->fetchColumn());
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('doctor_hospitals_availability_next_sort_order')) {
    function doctor_hospitals_availability_next_sort_order(int $doctor_id): int
    {
        global $pdo;

        if ($doctor_id <= 0 || !doctor_component_table_exists('chambers')) {
            return 1;
        }

        try {
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM chambers WHERE doctor_id = :doctor_id");
            $stmt->execute([':doctor_id' => $doctor_id]);

            return max(1, (int)$stmt->fetchColumn());
        } catch (Throwable $e) {
            return 1;
        }
    }
}

if (!function_exists('doctor_hospitals_availability_chamber_exists')) {
    function doctor_hospitals_availability_chamber_exists(int $doctor_id, int $hospital_id, int $ignore_chamber_id = 0): bool
    {
        global $pdo;

        if ($doctor_id <= 0 || $hospital_id <= 0 || !doctor_component_table_exists('chambers')) {
            return false;
        }

        try {
            $sql = "SELECT id FROM chambers WHERE doctor_id = :doctor_id AND hospital_id = :hospital_id";
            $params = [':doctor_id' => $doctor_id, ':hospital_id' => $hospital_id];

            if ($ignore_chamber_id > 0) {
                $sql .= " AND id != :ignore_chamber_id";
                $params[':ignore_chamber_id'] = $ignore_chamber_id;
            }

            $sql .= " LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('doctor_hospitals_availability_fee')) {
    function doctor_hospitals_availability_fee($fee): string
    {
        $fee = (float)$fee;

        if ($fee <= 0) {
            return '';
        }

        return '৳' . rtrim(rtrim(number_format($fee, 2, '.', ''), '0'), '.');
    }
}


if (!function_exists('doctor_hospitals_availability_normalize_status')) {
    function doctor_hospitals_availability_normalize_status($status): string
    {
        $status = strtolower(trim((string)$status));

        return $status === 'inactive' ? 'inactive' : 'active';
    }
}

if (!function_exists('doctor_hospitals_availability_handle_post')) {
    function doctor_hospitals_availability_handle_post(int $id, $doctor): void
    {
        global $pdo;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = $_POST['form_action'] ?? '';

        if (!in_array($action, ['save_chamber', 'add_chamber', 'update_chamber', 'delete_chamber'], true)) {
            return;
        }

        $self = basename($_SERVER['PHP_SELF']);

        if ($action === 'add_chamber') {
            redirect($self . '?id=' . $id . '&error=' . urlencode('Please fill chamber options and click Save Chamber.'));
        }

        if (!$id || !$doctor) {
            redirect($self . '?error=' . urlencode($action === 'save_chamber' ? 'Please save the doctor first, then add a chamber.' : 'Invalid doctor.'));
        }

        if ($action === 'delete_chamber') {
            $chamber_id = (int)($_POST['chamber_id'] ?? 0);

            if ($chamber_id <= 0) {
                redirect($self . '?id=' . $id . '&error=' . urlencode('Invalid chamber.'));
            }

            try {
                $stmt = $pdo->prepare("DELETE FROM chambers WHERE id = :id AND doctor_id = :doctor_id");
                $stmt->execute([':id' => $chamber_id, ':doctor_id' => $id]);
                redirect($self . '?id=' . $id . '&saved=chamber_deleted');
            } catch (PDOException $e) {
                redirect($self . '?id=' . $id . '&error=' . urlencode('Chamber could not be deleted. Error: ' . $e->getMessage()));
            }
        }

        if ($action === 'save_chamber') {
            $hospital_id = (int)($_POST['chamber_hospital_id'] ?? 0);

            if ($hospital_id <= 0) {
                redirect($self . '?id=' . $id . '&error=' . urlencode('Please select a hospital.'));
            }

            if (doctor_hospitals_availability_chamber_exists($id, $hospital_id)) {
                $hospital_name = doctor_hospitals_availability_get_hospital_name($hospital_id);
                redirect($self . '?id=' . $id . '&error=' . urlencode(($hospital_name !== '' ? $hospital_name . ' is' : 'This hospital is') . ' already added as a chamber for this doctor.'));
            }

            try {
                $available_days = $_POST['available_days'] ?? [];
                if (!is_array($available_days)) {
                    $available_days = [];
                }

                $address_bn = trim((string)($_POST['chamber_address_bn'] ?? ''));
                $schedule_bn = trim((string)($_POST['schedule_bn'] ?? ''));

                $stmt = $pdo->prepare("
                    INSERT INTO chambers
                    (
                        doctor_id, hospital_id, address, address_bn,
                        visiting_hours, visiting_hours_bn, consultation_fee, appointment_phone,
                        schedule, schedule_bn, available_days,
                        available_from, available_to, is_closed, status, sort_order,
                        created_at
                    )
                    VALUES
                    (
                        :doctor_id, :hospital_id, :address, :address_bn,
                        :visiting_hours, :visiting_hours_bn, :consultation_fee, :appointment_phone,
                        :schedule, :schedule_bn, :available_days,
                        :available_from, :available_to, :is_closed, :status, :sort_order,
                        NOW()
                    )
                ");

                $stmt->execute([
                    ':doctor_id' => $id,
                    ':hospital_id' => $hospital_id,
                    ':address' => trim((string)($_POST['chamber_address'] ?? '')),
                    ':address_bn' => $address_bn,
                    ':visiting_hours' => trim((string)($_POST['schedule'] ?? '')),
                    ':visiting_hours_bn' => $schedule_bn,
                    ':consultation_fee' => (float)($_POST['chamber_consultation_fee'] ?? 0),
                    ':appointment_phone' => trim((string)($_POST['chamber_appointment_phone'] ?? '')),
                    ':schedule' => trim((string)($_POST['schedule'] ?? '')),
                    ':schedule_bn' => $schedule_bn,
                    ':available_days' => doctor_hospitals_availability_clean_days($available_days),
                    ':available_from' => trim((string)($_POST['available_from'] ?? '')),
                    ':available_to' => trim((string)($_POST['available_to'] ?? '')),
                    ':is_closed' => isset($_POST['is_closed']) ? 1 : 0,
                    ':status' => doctor_hospitals_availability_normalize_status($_POST['chamber_status'] ?? 'active'),
                    ':sort_order' => ((int)($_POST['chamber_sort_order'] ?? 0) > 0 ? (int)$_POST['chamber_sort_order'] : doctor_hospitals_availability_next_sort_order($id)),
                ]);

                if (empty($doctor['hospital_id'])) {
                    $stmt = $pdo->prepare("UPDATE doctors SET hospital_id = :hospital_id WHERE id = :id");
                    $stmt->execute([':hospital_id' => $hospital_id, ':id' => $id]);
                }

                redirect($self . '?id=' . $id . '&saved=chamber');
            } catch (PDOException $e) {
                redirect($self . '?id=' . $id . '&error=' . urlencode('Chamber could not be saved. Error: ' . $e->getMessage()));
            }
        }

        if ($action === 'update_chamber') {
            $chamber_id = (int)($_POST['chamber_id'] ?? 0);

            if ($chamber_id <= 0) {
                redirect($self . '?id=' . $id . '&error=' . urlencode('Invalid chamber.'));
            }

            try {
                $available_days = $_POST['available_days'] ?? [];
                if (!is_array($available_days)) {
                    $available_days = [];
                }
                $address_bn = trim((string)($_POST['chamber_address_bn'] ?? ''));
                $schedule_bn = trim((string)($_POST['schedule_bn'] ?? ''));

                $stmt = $pdo->prepare("
                    UPDATE chambers SET
                        address = :address,
                        address_bn = :address_bn,
                        visiting_hours = :visiting_hours,
                        visiting_hours_bn = :visiting_hours_bn,
                        consultation_fee = :consultation_fee,
                        appointment_phone = :appointment_phone,
                        schedule = :schedule,
                        schedule_bn = :schedule_bn,
                        available_days = :available_days,
                        available_from = :available_from,
                        available_to = :available_to,
                        is_closed = :is_closed,
                        status = :status,
                        sort_order = :sort_order
                    WHERE id = :id AND doctor_id = :doctor_id
                ");

                $stmt->execute([
                    ':address' => trim((string)($_POST['chamber_address'] ?? '')),
                    ':address_bn' => $address_bn,
                    ':visiting_hours' => trim((string)($_POST['schedule'] ?? '')),
                    ':visiting_hours_bn' => $schedule_bn,
                    ':consultation_fee' => (float)($_POST['chamber_consultation_fee'] ?? 0),
                    ':appointment_phone' => trim((string)($_POST['chamber_appointment_phone'] ?? '')),
                    ':schedule' => trim((string)($_POST['schedule'] ?? '')),
                    ':schedule_bn' => $schedule_bn,
                    ':available_days' => doctor_hospitals_availability_clean_days($available_days),
                    ':available_from' => trim((string)($_POST['available_from'] ?? '')),
                    ':available_to' => trim((string)($_POST['available_to'] ?? '')),
                    ':is_closed' => isset($_POST['is_closed']) ? 1 : 0,
                    ':status' => doctor_hospitals_availability_normalize_status($_POST['chamber_status'] ?? 'active'),
                    ':sort_order' => (int)($_POST['chamber_sort_order'] ?? 0),
                    ':id' => $chamber_id,
                    ':doctor_id' => $id,
                ]);

                redirect($self . '?id=' . $id . '&saved=chamber_updated');
            } catch (PDOException $e) {
                redirect($self . '?id=' . $id . '&error=' . urlencode('Chamber could not be updated. Error: ' . $e->getMessage()));
            }
        }
    }
}


if (!function_exists('doctor_hospitals_availability_fetch_location_rows')) {
    function doctor_hospitals_availability_fetch_location_rows(string $table, string $parent_column = '', int $parent_id = 0): array
    {
        global $pdo;

        $table = doctor_hospitals_availability_safe_identifier($table);
        $parent_column = $parent_column !== '' ? doctor_hospitals_availability_safe_identifier($parent_column) : '';

        if (
            !doctor_hospitals_availability_has_pdo() ||
            $table === '' ||
            !in_array($table, ['divisions', 'districts', 'thanas'], true) ||
            !doctor_component_table_exists($table)
        ) {
            return [];
        }

        $name_select = 'id';

        if (doctor_component_column_exists($table, 'name_en')) {
            $name_select .= ', name_en AS name';
        } elseif (doctor_component_column_exists($table, 'name')) {
            $name_select .= ', name AS name';
        } else {
            return [];
        }

        if (doctor_component_column_exists($table, 'name_bn')) {
            $name_select .= ', name_bn';
        }

        $sql = "SELECT {$name_select} FROM `{$table}`";
        $params = [];
        $where = [];

        if ($parent_column !== '' && $parent_id > 0 && doctor_component_column_exists($table, $parent_column)) {
            $where[] = "`{$parent_column}` = :parent_id";
            $params[':parent_id'] = $parent_id;
        }

        if (doctor_component_column_exists($table, 'status')) {
            $where[] = "(`status` IS NULL OR `status` = '' OR `status` = 'active')";
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY name ASC';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return array_map(function ($row) {
                $name = trim((string)($row['name'] ?? ''));

                return [
                    'id' => (int)($row['id'] ?? 0),
                    'name' => $name,
                ];
            }, $rows);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('doctor_hospitals_availability_search_hospitals')) {
    function doctor_hospitals_availability_search_hospitals(array $filters): array
    {
        global $pdo;

        if (!doctor_hospitals_availability_has_pdo() || !doctor_component_table_exists('hospitals')) {
            return [];
        }

        $select = ['h.id'];
        $select[] = doctor_component_column_exists('hospitals', 'name') ? 'h.name' : "CONCAT('Hospital #', h.id) AS name";
        $select[] = doctor_component_column_exists('hospitals', 'slug') ? 'h.slug' : "'' AS slug";
        $select[] = doctor_component_column_exists('hospitals', 'address') ? 'h.address' : "'' AS address";
        $select[] = doctor_component_column_exists('hospitals', 'phone') ? 'h.phone' : "'' AS phone";

        $joins = [];
        $location_parts = [];

        if (doctor_component_column_exists('hospitals', 'district_id') && doctor_component_table_exists('districts')) {
            $joins[] = 'LEFT JOIN districts d ON d.id = h.district_id';

            if (doctor_component_column_exists('districts', 'name_en')) {
                $location_parts[] = 'd.name_en';
            } elseif (doctor_component_column_exists('districts', 'name')) {
                $location_parts[] = 'd.name';
            }
        }

        if (doctor_component_column_exists('hospitals', 'thana_id') && doctor_component_table_exists('thanas')) {
            $joins[] = 'LEFT JOIN thanas t ON t.id = h.thana_id';

            if (doctor_component_column_exists('thanas', 'name_en')) {
                $location_parts[] = 't.name_en';
            } elseif (doctor_component_column_exists('thanas', 'name')) {
                $location_parts[] = 't.name';
            }
        }

        if (!empty($location_parts)) {
            $select[] = 'CONCAT_WS(\', \', ' . implode(', ', $location_parts) . ') AS location_name';
        } else {
            $select[] = "'' AS location_name";
        }

        $where = [];
        $params = [];

        $query = trim((string)($filters['q'] ?? ''));

        if ($query !== '' && doctor_component_column_exists('hospitals', 'name')) {
            $where[] = 'h.name LIKE :q';
            $params[':q'] = '%' . $query . '%';
        }

        foreach ([
            'division_id' => (int)($filters['division_id'] ?? 0),
            'district_id' => (int)($filters['district_id'] ?? 0),
            'thana_id' => (int)($filters['thana_id'] ?? 0),
        ] as $column => $value) {
            if ($value > 0 && doctor_component_column_exists('hospitals', $column)) {
                $where[] = 'h.`' . $column . '` = :' . $column;
                $params[':' . $column] = $value;
            }
        }

        if (doctor_component_column_exists('hospitals', 'status')) {
            $where[] = "(h.status IS NULL OR h.status = '' OR h.status = 'active')";
        }

        $limit = (int)($filters['limit'] ?? 50);
        $limit = max(1, min(100, $limit));

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM hospitals h ' . implode(' ', $joins);

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY h.name ASC LIMIT ' . $limit;

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return array_map(function ($row) {
                $id = (int)($row['id'] ?? 0);
                $name = trim((string)($row['name'] ?? 'Hospital'));
                $slug = trim((string)($row['slug'] ?? ''));
                $location = trim((string)($row['location_name'] ?? ''));
                $address = trim((string)($row['address'] ?? ''));
                $phone = trim((string)($row['phone'] ?? ''));
                $meta = array_values(array_filter([$location, $address, $phone]));

                return [
                    'id' => $id,
                    'name' => $name,
                    'location' => implode(' | ', $meta),
                    'view_url' => $slug !== '' ? '../hospital/' . rawurlencode($slug) : 'hospitals.php?id=' . $id,
                ];
            }, $rows);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('doctor_hospitals_availability_handle_ajax')) {
    function doctor_hospitals_availability_handle_ajax(): void
    {
        $action = trim((string)($_GET['dha_ajax'] ?? ''));

        if ($action === '') {
            return;
        }

        if ($action === 'districts') {
            doctor_hospitals_availability_json(
                doctor_hospitals_availability_fetch_location_rows('districts', 'division_id', (int)($_GET['division_id'] ?? 0))
            );
        }

        if ($action === 'thanas') {
            doctor_hospitals_availability_json(
                doctor_hospitals_availability_fetch_location_rows('thanas', 'district_id', (int)($_GET['district_id'] ?? 0))
            );
        }

        if ($action === 'hospitals') {
            doctor_hospitals_availability_json([
                'success' => true,
                'items' => doctor_hospitals_availability_search_hospitals($_GET),
            ]);
        }

        doctor_hospitals_availability_json([
            'success' => false,
            'message' => 'Invalid AJAX action.',
        ]);
    }
}

doctor_hospitals_availability_ensure_table();
doctor_hospitals_availability_handle_ajax();

if (!function_exists('doctor_hospitals_availability_render')) {
    function doctor_hospitals_availability_render(array $context = []): void
    {
        extract($context, EXTR_SKIP);
        doctor_hospitals_availability_css();
        ?>
        <div class="lite-form-card" style="margin-bottom:22px;">
            <div class="lite-form-card-header">
                <h2>Hospitals &amp; Availability</h2>
                <p>Select Division, District, Thana and Hospital Name, then click Add. Hospital search works from the full database.</p>
            </div>

            <div class="lite-admin-form">
                <div class="lite-section">
                    <div class="lite-section-title">
                        <h3>Add Hospital / Chamber</h3>
                        <span>Lightweight AJAX hospital search</span>
                    </div>

                    <?php if ($id): ?>
                        <form method="POST" class="wp-style-chamber-box" id="addChamberAjaxForm">
                            <input type="hidden" name="form_action" value="add_chamber" id="addChamberAction">

                            <div class="wp-style-chamber-head">
                                Select Division → District → Thana → Hospital Name
                            </div>

                            <div class="wp-style-chamber-body">
                                <div class="wp-chain-grid">
                                    <div>
                                        <label>Division</label>
                                        <select id="doctor_chamber_division">
                                            <option value="">— Select Division —</option>
                                            <?php foreach (($doctor_form_divisions ?? []) as $division): ?>
                                                <option value="<?= e((string)$division['id']) ?>"><?= e($division['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label>District</label>
                                        <select id="doctor_chamber_district" disabled>
                                            <option value="">— Select District —</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label>Thana</label>
                                        <select id="doctor_chamber_thana" disabled>
                                            <option value="">— All Thanas —</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label>Hospital Name</label>
                                        <div class="wp-search-dropdown" id="doctor_chamber_hospital_dropdown">
                                            <input
                                                type="text"
                                                class="wp-search-dropdown-input"
                                                id="doctor_chamber_hospital_search"
                                                placeholder="— Select or search hospital —"
                                                autocomplete="off"
                                            >
                                            <span class="wp-search-dropdown-arrow">▼</span>
                                            <input type="hidden" name="chamber_hospital_id" id="doctor_chamber_hospital" value="">
                                            <input type="hidden" id="doctor_chamber_hospital_view_url" value="">
                                            <div class="wp-search-dropdown-list" id="doctor_chamber_hospital_list"></div>
                                        </div>
                                    </div>

                                    <div>
                                        <button type="submit" class="wp-admin-button">Add</button>
                                    </div>
                                </div>

                                <div class="wp-preview-note" id="doctor_chamber_preview">
                                    Select Division → District → Thana → Hospital Name. One hospital can be added only once for the same doctor.
                                </div>
                            </div>
                        </form>

                        <div class="lite-chamber-table-wrap">
                            <table class="lite-chamber-table">
                                <thead>
                                    <tr>
                                        <th style="width:70px;text-align:center;">#</th>
                                        <th>Hospital</th>
                                        <th>Appointment</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="doctorChamberList">
                                    <?php if (!empty($doctor_chambers)): ?>
                                        <?php foreach ($doctor_chambers as $chamber_index => $chamber): ?>
                                            <?php
                                                $selected_days = doctor_hospitals_availability_selected_days((string)($chamber['available_days'] ?? ''));
                                                $hospital_view_url = doctor_hospitals_availability_hospital_url([
                                                    'hospital_id' => $chamber['hospital_id'] ?? 0,
                                                    'hospital_slug' => $chamber['hospital_slug'] ?? '',
                                                ]);
                                                $hospital_edit_url = doctor_hospitals_availability_hospital_edit_url([
                                                    'hospital_id' => $chamber['hospital_id'] ?? 0,
                                                ]);
                                                $chamber_status = doctor_hospitals_availability_normalize_status($chamber['status'] ?? 'active');
                                                $hospital_title = trim((string)($chamber['hospital_name'] ?? ''));
                                                $display_title = $hospital_title !== '' ? $hospital_title : 'Hospital';
                                                $chamber_number = (int)$chamber_index + 1;
                                                $chamber_color_class = 'chamber-color-' . (((max(1, $chamber_number) - 1) % 9) + 1);
                                                $display_sort_order = (int)($chamber['sort_order'] ?? 0);
                                                if ($display_sort_order <= 0) {
                                                    $display_sort_order = $chamber_number;
                                                }
                                                $hospital_district_name = trim((string)($chamber['hospital_district_name'] ?? ''));
                                                $hospital_thana_name = trim((string)($chamber['hospital_thana_name'] ?? ''));
                                                $hospital_subtitle_parts = array_filter([$hospital_thana_name, $hospital_district_name]);
                                                $hospital_subtitle = implode(', ', $hospital_subtitle_parts);
                                                $display_fee = doctor_hospitals_availability_fee($chamber['consultation_fee'] ?? 0);
                                                $display_visiting_time = doctor_hospitals_availability_format_time_range($chamber['available_from'] ?? '', $chamber['available_to'] ?? '');
                                                $display_visiting_days = doctor_hospitals_availability_format_days((string)($chamber['available_days'] ?? ''));
                                                $display_status = $chamber_status === 'active' ? 'Active' : 'Inactive';
                                                $is_closed = !empty($chamber['is_closed']);

                                                if ($is_closed) {
                                                    $display_status .= ' / Closed';
                                                }

                                                $edit_row_id = 'chamberEditRow' . (int)($chamber['id'] ?? 0);
                                            ?>

                                            <tr class="lite-chamber-entry" data-hospital-id="<?= e((string)($chamber['hospital_id'] ?? 0)) ?>" data-sort-order="<?= e((string)$display_sort_order) ?>">
                                                <td class="lite-serial-cell"><span class="lite-serial-number"><?= e((string)$chamber_number) ?></span></td>
                                                <td>
                                                    <div class="lite-chamber-name-cell">
                                                        <div class="lite-chamber-name-main"><strong><?= e($display_title) ?></strong></div>
                                                        <?php if ($hospital_subtitle !== ''): ?>
                                                            <div class="lite-chamber-sub"><?= e($hospital_subtitle) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td><?= !empty($chamber['appointment_phone']) ? e($chamber['appointment_phone']) : '—' ?></td>
                                                <td><?= $display_visiting_time !== '' ? e($display_visiting_time) : '—' ?></td>
                                                <td>
                                                    <span class="lite-status-badge <?= $chamber_status === 'active' ? 'lite-status-active' : 'lite-status-inactive' ?>">
                                                        <?= e($display_status) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="lite-table-action-group">
                                                        <button type="button" class="lite-table-btn" data-chamber-edit-toggle="<?= e($edit_row_id) ?>">Edit</button>

                                                        <div class="lite-chamber-menu-wrap" data-chamber-menu-wrap>
                                                            <button type="button" class="lite-kebab" data-chamber-menu-toggle aria-label="More actions">
                                                                <i class="fa fa-ellipsis-v fa-solid fa-ellipsis-vertical"></i>
                                                            </button>

                                                            <div class="lite-chamber-menu">
                                                                <a href="<?= e($hospital_view_url) ?>" target="_blank" rel="noopener" class="lite-menu-link">
                                                                    <span class="lite-menu-icon">👁</span>
                                                                    <span>View</span>
                                                                </a>

                                                                <a href="<?= e($hospital_edit_url) ?>" target="_blank" rel="noopener" class="lite-menu-link">
                                                                    <span class="lite-menu-icon">✎</span>
                                                                    <span>Hospital Edit</span>
                                                                </a>

                                                                <form method="POST" onsubmit="return confirm('Delete this chamber?');">
                                                                    <input type="hidden" name="form_action" value="delete_chamber">
                                                                    <input type="hidden" name="chamber_id" value="<?= e((string)$chamber['id']) ?>">
                                                                    <button type="submit" class="lite-menu-button">
                                                                        <span class="lite-menu-icon">🗑</span>
                                                                        <span>Delete</span>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr class="lite-chamber-edit-row" id="<?= e($edit_row_id) ?>">
                                                <td colspan="6">
                                                    <form method="POST">
                                                        <input type="hidden" name="form_action" value="update_chamber">
                                                        <input type="hidden" name="chamber_id" value="<?= e((string)$chamber['id']) ?>">

                                                        <div class="wp-schedule-grid">
                                                            <div><label>Appointment Phone</label><input type="text" name="chamber_appointment_phone" value="<?= e($chamber['appointment_phone'] ?? '') ?>" placeholder="+880..."></div>
                                                            <div><label>Consultation Fee</label><input type="number" step="0.01" name="chamber_consultation_fee" value="<?= e((string)($chamber['consultation_fee'] ?? '')) ?>" placeholder="1000"></div>
                                                            <div><label>From</label><input type="time" name="available_from" value="<?= e($chamber['available_from'] ?? '') ?>"></div>
                                                            <div><label>To</label><input type="time" name="available_to" value="<?= e($chamber['available_to'] ?? '') ?>"></div>
                                                            <div><label>Sort Order</label><input type="number" name="chamber_sort_order" value="<?= e((string)$display_sort_order) ?>"></div>
                                                            <div>
                                                                <label>Status</label>
                                                                <select name="chamber_status">
                                                                    <option value="active" <?= $chamber_status === 'active' ? 'selected' : '' ?>>Active</option>
                                                                    <option value="inactive" <?= $chamber_status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                                </select>
                                                            </div>

                                                            <div class="wp-field-pair">
                                                                <div><label>Schedule</label><input type="text" name="schedule" value="<?= e($chamber['schedule'] ?? $chamber['visiting_hours'] ?? '') ?>" placeholder="Saturday to Thursday 3pm to 9pm (Closed: Friday)"></div>
                                                                <div><label>Schedule Bangla</label><input type="text" name="schedule_bn" value="<?= e($chamber['schedule_bn'] ?? $chamber['visiting_hours_bn'] ?? '') ?>" placeholder="শনিবার থেকে বৃহস্পতিবার বিকাল ৩টা থেকে রাত ৯টা (শুক্রবার বন্ধ)"></div>
                                                            </div>

                                                            <div class="wp-field-pair">
                                                                <div><label>Chamber Address</label><input type="text" name="chamber_address" value="<?= e($chamber['address'] ?? '') ?>" placeholder="Chamber address or room/floor details"></div>
                                                                <div><label>Chamber Address Bangla</label><input type="text" name="chamber_address_bn" value="<?= e($chamber['address_bn'] ?? '') ?>" placeholder="চেম্বারের ঠিকানা বা রুম/ফ্লোরের তথ্য"></div>
                                                            </div>
                                                        </div>

                                                        <div class="wp-days-row">
                                                            <?php
                                                                $days = [
                                                                    'sat' => 'Saturday',
                                                                    'sun' => 'Sunday',
                                                                    'mon' => 'Monday',
                                                                    'tue' => 'Tuesday',
                                                                    'wed' => 'Wednesday',
                                                                    'thu' => 'Thursday',
                                                                    'fri' => 'Friday',
                                                                ];
                                                            ?>

                                                            <?php foreach ($days as $day_key => $day_label): ?>
                                                                <label class="wp-day-pill">
                                                                    <input type="checkbox" name="available_days[]" value="<?= e($day_key) ?>" <?= in_array($day_key, $selected_days, true) ? 'checked' : '' ?>>
                                                                    <span class="wp-day-check"></span>
                                                                    <span class="wp-day-text"><?= e($day_label) ?> / <?= e([
                                                                        'sat' => 'শনিবার',
                                                                        'sun' => 'রবিবার',
                                                                        'mon' => 'সোমবার',
                                                                        'tue' => 'মঙ্গলবার',
                                                                        'wed' => 'বুধবার',
                                                                        'thu' => 'বৃহস্পতিবার',
                                                                        'fri' => 'শুক্রবার',
                                                                    ][$day_key] ?? '') ?></span>
                                                                </label>
                                                            <?php endforeach; ?>

                                                            <label class="wp-day-pill wp-day-pill-closed">
                                                                <input type="checkbox" name="is_closed" value="1" <?= !empty($chamber['is_closed']) ? 'checked' : '' ?>>
                                                                <span class="wp-day-check"></span>
                                                                <span class="wp-day-text">Closed / বন্ধ</span>
                                                            </label>
                                                        </div>

                                                        <div class="lite-edit-form-actions">
                                                            <button type="submit" class="lite-btn lite-btn-primary">Update Chamber</button>
                                                            <button type="button" class="lite-btn lite-btn-light" data-chamber-edit-cancel>Close</button>
                                                        </div>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr id="noChamberMessage"><td colspan="6" class="lite-chamber-empty-cell">No saved chamber yet. First select Division, District, Thana and Hospital Name, then click Add to open the option form.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="lite-auto-box">
                            Save the doctor first. After saving, you can add hospital availability from this same page.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        (function() {
            const chamberDivision = document.getElementById('doctor_chamber_division');
            const chamberDistrict = document.getElementById('doctor_chamber_district');
            const chamberThana = document.getElementById('doctor_chamber_thana');

            const hospitalDropdown = document.getElementById('doctor_chamber_hospital_dropdown');
            const hospitalSearch = document.getElementById('doctor_chamber_hospital_search');
            const hospitalHidden = document.getElementById('doctor_chamber_hospital');
            const hospitalViewUrl = document.getElementById('doctor_chamber_hospital_view_url');
            const hospitalList = document.getElementById('doctor_chamber_hospital_list');

            const chamberPreview = document.getElementById('doctor_chamber_preview');
            const addChamberAjaxForm = document.getElementById('addChamberAjaxForm');
            const doctorChamberList = document.getElementById('doctorChamberList');
            const noChamberMessage = document.getElementById('noChamberMessage');

            function doctorHospitalsAvailabilityAjaxUrl(action, params = {}) {
                const url = new URL(window.location.href);
                url.searchParams.set('dha_ajax', action);

                Object.keys(params || {}).forEach(function(key) {
                    url.searchParams.set(key, params[key]);
                });

                return url.toString();
            }

            let hospitalSearchTimer = null;
            let lastHospitalQuery = '';

            function resetDoctorChamberSelect(select, placeholder) {
                if (!select) return;
                select.innerHTML = `<option value="">${placeholder}</option>`;
                select.disabled = true;
            }

            function fillDoctorChamberSelect(select, items, placeholder) {
                if (!select) return;
                select.innerHTML = `<option value="">${placeholder}</option>`;

                items.forEach(function(item) {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = item.name;
                    select.appendChild(option);
                });

                select.disabled = false;
            }

            function escapeHtml(value) {
                return String(value || '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function openHospitalDropdown() {
                if (hospitalDropdown) hospitalDropdown.classList.add('is-open');
            }

            function closeHospitalDropdown() {
                if (hospitalDropdown) hospitalDropdown.classList.remove('is-open');
            }

            function clearHospitalSelection(keepText = false) {
                if (hospitalHidden) hospitalHidden.value = '';
                if (hospitalViewUrl) hospitalViewUrl.value = '';
                if (hospitalSearch) {
                    hospitalSearch.dataset.location = '';
                    if (!keepText) hospitalSearch.value = '';
                }
                updateDoctorChamberPreview();
            }

            function renderHospitalList(items, message = '') {
                if (!hospitalList) return;

                hospitalList.innerHTML = '';

                if (message) {
                    hospitalList.innerHTML = `<div class="wp-search-dropdown-empty">${escapeHtml(message)}</div>`;
                    return;
                }

                if (!Array.isArray(items) || items.length === 0) {
                    hospitalList.innerHTML = '<div class="wp-search-dropdown-empty">No hospital found</div>';
                    return;
                }

                items.forEach(function(item) {
                    const row = document.createElement('div');
                    row.className = 'wp-search-dropdown-item';
                    row.dataset.id = item.id || '';
                    row.dataset.name = item.name || '';
                    row.dataset.viewUrl = item.view_url || '';
                    row.innerHTML = `
                        <strong>${escapeHtml(item.name || 'Hospital')}</strong>
                        <span class="wp-search-dropdown-meta">${escapeHtml(item.location || '')}</span>
                    `;

                    row.addEventListener('click', function() {
                        if (hospitalHidden) hospitalHidden.value = String(item.id || '');
                        if (hospitalViewUrl) hospitalViewUrl.value = String(item.view_url || '');
                        if (hospitalSearch) {
                            hospitalSearch.value = String(item.name || '');
                            hospitalSearch.dataset.location = String(item.location || '');
                        }

                        closeHospitalDropdown();
                        updateDoctorChamberPreview();
                    });

                    hospitalList.appendChild(row);
                });
            }

            async function searchHospitals(force = false) {
                const query = hospitalSearch ? hospitalSearch.value.trim() : '';
                const divisionId = chamberDivision ? chamberDivision.value : '';
                const districtId = chamberDistrict ? chamberDistrict.value : '';
                const thanaId = chamberThana ? chamberThana.value : '';

                const key = [query, divisionId, districtId, thanaId].join('|');

                if (!force && key === lastHospitalQuery) {
                    return;
                }

                lastHospitalQuery = key;
                openHospitalDropdown();
                renderHospitalList([], 'Searching hospitals...');

                const params = new URLSearchParams();
                params.set('q', query);
                params.set('division_id', divisionId);
                params.set('district_id', districtId);
                params.set('thana_id', thanaId);
                params.set('limit', '50');

                try {
                    const response = await fetch(doctorHospitalsAvailabilityAjaxUrl('hospitals') + '&' + params.toString());
                    const data = await response.json();

                    if (!data || data.success !== true) {
                        renderHospitalList([], data && data.message ? data.message : 'No hospital found');
                        return;
                    }

                    renderHospitalList(data.items || []);
                } catch (error) {
                    console.error(error);
                    renderHospitalList([], 'Hospital search failed');
                }
            }

            function scheduleHospitalSearch(force = false) {
                clearTimeout(hospitalSearchTimer);
                hospitalSearchTimer = setTimeout(function() {
                    searchHospitals(force);
                }, 180);
            }

            function updateDoctorChamberPreview() {
                if (!chamberPreview) return;

                const parts = [];

                if (chamberDivision && chamberDivision.selectedIndex > 0) {
                    parts.push(chamberDivision.options[chamberDivision.selectedIndex].textContent.trim());
                }

                if (chamberDistrict && chamberDistrict.selectedIndex > 0) {
                    parts.push(chamberDistrict.options[chamberDistrict.selectedIndex].textContent.trim());
                }

                if (chamberThana && chamberThana.selectedIndex > 0) {
                    parts.push(chamberThana.options[chamberThana.selectedIndex].textContent.trim());
                }

                if (hospitalHidden && hospitalHidden.value && hospitalSearch && hospitalSearch.value.trim()) {
                    parts.push(hospitalSearch.value.trim());
                }

                chamberPreview.textContent = parts.length
                    ? parts.join(' → ')
                    : 'Select Division → District → Thana → Hospital Name.';
            }

            async function loadDoctorChamberDistricts(divisionId) {
                resetDoctorChamberSelect(chamberDistrict, '— Select District —');
                resetDoctorChamberSelect(chamberThana, '— All Thanas —');

                if (!divisionId) {
                    scheduleHospitalSearch(true);
                    updateDoctorChamberPreview();
                    return;
                }

                try {
                    const response = await fetch(doctorHospitalsAvailabilityAjaxUrl('districts', {division_id: divisionId, lang: 'en'}));
                    const districts = await response.json();
                    fillDoctorChamberSelect(chamberDistrict, Array.isArray(districts) ? districts : [], '— Select District —');
                } catch (error) {
                    console.error(error);
                }

                scheduleHospitalSearch(true);
                updateDoctorChamberPreview();
            }

            async function loadDoctorChamberThanas(districtId) {
                resetDoctorChamberSelect(chamberThana, '— All Thanas —');

                if (!districtId) {
                    scheduleHospitalSearch(true);
                    updateDoctorChamberPreview();
                    return;
                }

                try {
                    const response = await fetch(doctorHospitalsAvailabilityAjaxUrl('thanas', {district_id: districtId, lang: 'en'}));
                    const thanas = await response.json();
                    fillDoctorChamberSelect(chamberThana, Array.isArray(thanas) ? thanas : [], '— All Thanas —');
                } catch (error) {
                    console.error(error);
                }

                scheduleHospitalSearch(true);
                updateDoctorChamberPreview();
            }

            function showDoctorFormMessage(type, message) {
                let alertBox = document.querySelector('.lite-alert');

                if (!alertBox) {
                    alertBox = document.createElement('div');
                    alertBox.className = 'lite-alert';
                    alertBox.innerHTML = '<div class="lite-alert-icon"></div><div class="lite-alert-content"><h3></h3><p></p></div>';

                    const page = document.querySelector('.lite-doctor-page');
                    if (page) page.prepend(alertBox);
                }

                alertBox.className = `lite-alert ${type}`;
                alertBox.querySelector('.lite-alert-icon').textContent = type === 'success' ? '✓' : '!';
                alertBox.querySelector('.lite-alert-content h3').textContent = type === 'success' ? 'Success' : 'Error';
                alertBox.querySelector('.lite-alert-content p').textContent = message;
            }

            function doctorChamberAlreadyExists(hospitalId) {
                if (!doctorChamberList || !hospitalId) return false;

                return Array.from(doctorChamberList.querySelectorAll('[data-hospital-id]')).some(function(card) {
                    return String(card.getAttribute('data-hospital-id') || '') === String(hospitalId);
                });
            }

            function getNextChamberSortOrder() {
                if (!doctorChamberList) return 1;

                return doctorChamberList.querySelectorAll('.lite-chamber-entry').length + 1;
            }

            function renumberChamberRows() {
                if (!doctorChamberList) return;

                const rows = Array.from(doctorChamberList.querySelectorAll('.lite-chamber-entry'));

                rows.forEach(function(row, index) {
                    const number = index + 1;
                    const numberEl = row.querySelector('.lite-serial-number');

                    if (numberEl) {
                        numberEl.textContent = String(number);
                    }

                    row.setAttribute('data-display-serial', String(number));

                    if (row.classList.contains('unsaved-chamber')) {
                        row.setAttribute('data-sort-order', String(number));

                        const editRow = row.nextElementSibling && row.nextElementSibling.classList.contains('lite-chamber-edit-row')
                            ? row.nextElementSibling
                            : null;

                        const sortInput = editRow ? editRow.querySelector('input[name="chamber_sort_order"]') : null;

                        if (sortInput) {
                            sortInput.value = String(number);
                        }
                    }
                });
            }

            function getExistingChamberEntry(hospitalId) {
                if (!doctorChamberList || !hospitalId) return null;

                return Array.from(doctorChamberList.querySelectorAll('.lite-chamber-entry')).find(function(row) {
                    return String(row.getAttribute('data-hospital-id') || '') === String(hospitalId);
                }) || null;
            }

            function openExistingChamberEdit(row) {
                if (!row) return;

                const toggle = row.querySelector('[data-chamber-edit-toggle]');
                const targetId = toggle ? toggle.getAttribute('data-chamber-edit-toggle') : '';
                const editRow = targetId ? document.getElementById(targetId) : null;

                document.querySelectorAll('.lite-chamber-edit-row.is-open').forEach(function(openRow) {
                    if (openRow !== editRow) {
                        openRow.classList.remove('is-open');
                        const previousEntry = openRow.previousElementSibling;
                        if (previousEntry && previousEntry.classList.contains('lite-chamber-entry')) {
                            previousEntry.classList.remove('is-editing');
                        }
                    }
                });

                if (editRow) {
                    editRow.classList.add('is-open');
                    row.classList.add('is-editing');
                }

                row.classList.add('is-highlighted');
                row.scrollIntoView({behavior: 'smooth', block: 'center'});

                window.setTimeout(function() {
                    row.classList.remove('is-highlighted');
                }, 1600);
            }

            function buildUnsavedChamberCard(hospitalId, hospitalName, viewUrl, sortOrder, hospitalLocation = '') {
                const safeHospitalId = escapeHtml(hospitalId);
                const safeHospitalName = escapeHtml(hospitalName || 'Hospital / Chamber');
                const safeViewUrl = escapeHtml(viewUrl || 'hospitals.php?id=' + safeHospitalId);
                const safeEditUrl = escapeHtml('/admin/hospital-form.php?id=' + safeHospitalId);
                const safeSortOrder = Math.max(1, parseInt(sortOrder || '1', 10) || 1);
                const safeHospitalLocation = escapeHtml(hospitalLocation || 'Not saved yet. Fill options and click Save Chamber.');
                const safeColorClass = 'chamber-color-' + (((safeSortOrder - 1) % 9) + 1);
                const editRowId = 'newChamberEditRow' + safeHospitalId + '_' + Date.now();

                const days = [
                    ['sat', 'Saturday / শনিবার'],
                    ['sun', 'Sunday / রবিবার'],
                    ['mon', 'Monday / সোমবার'],
                    ['tue', 'Tuesday / মঙ্গলবার'],
                    ['wed', 'Wednesday / বুধবার'],
                    ['thu', 'Thursday / বৃহস্পতিবার'],
                    ['fri', 'Friday / শুক্রবার']
                ];

                const dayOptions = days.map(function(day) {
                    return `<label class="wp-day-pill"><input type="checkbox" name="available_days[]" value="${day[0]}"><span class="wp-day-check"></span><span class="wp-day-text">${day[1]}</span></label>`;
                }).join('');

                return `
                    <tr class="lite-chamber-entry unsaved-chamber is-editing" data-hospital-id="${safeHospitalId}" data-sort-order="${safeSortOrder}">
                        <td class="lite-serial-cell"><span class="lite-serial-number">${safeSortOrder}</span></td>
                        <td><div class="lite-chamber-name-cell"><div class="lite-chamber-name-main"><strong>${safeHospitalName}</strong></div><div class="lite-chamber-sub">${safeHospitalLocation}</div></div></td>
                        <td>—</td>
                        <td>—</td>
                        <td><span class="lite-status-badge lite-status-inactive">Unsaved</span></td>
                        <td>
                            <div class="lite-table-action-group">
                                <button type="button" class="lite-table-btn" data-chamber-edit-toggle="${editRowId}">Edit</button>

                                <div class="lite-chamber-menu-wrap" data-chamber-menu-wrap>
                                    <button type="button" class="lite-kebab" data-chamber-menu-toggle aria-label="More actions">
                                        <i class="fa fa-ellipsis-v fa-solid fa-ellipsis-vertical"></i>
                                    </button>

                                    <div class="lite-chamber-menu">
                                        <a href="${safeViewUrl}" target="_blank" rel="noopener" class="lite-menu-link">
                                            <span class="lite-menu-icon">👁</span>
                                            <span>View</span>
                                        </a>

                                        <a href="${safeEditUrl}" target="_blank" rel="noopener" class="lite-menu-link">
                                            <span class="lite-menu-icon">✎</span>
                                            <span>Hospital Edit</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr class="lite-chamber-edit-row is-open" id="${editRowId}">
                        <td colspan="6">
                            <form method="POST">
                                <input type="hidden" name="form_action" value="save_chamber">
                                <input type="hidden" name="chamber_hospital_id" value="${safeHospitalId}">

                                <div class="wp-schedule-grid">
                                    <div><label>Appointment Phone</label><input type="text" name="chamber_appointment_phone" placeholder="+880..."></div>
                                    <div><label>Consultation Fee</label><input type="number" step="0.01" name="chamber_consultation_fee" placeholder="1000"></div>
                                    <div><label>From</label><input type="time" name="available_from"></div>
                                    <div><label>To</label><input type="time" name="available_to"></div>
                                    <div><label>Sort Order</label><input type="number" name="chamber_sort_order" value="${safeSortOrder}"></div>
                                    <div>
                                        <label>Status</label>
                                        <select name="chamber_status">
                                            <option value="active" selected>Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </div>

                                    <div class="wp-field-pair">
                                        <div><label>Schedule</label><input type="text" name="schedule" placeholder="Saturday to Thursday 3pm to 9pm (Closed: Friday)"></div>
                                        <div><label>Schedule Bangla</label><input type="text" name="schedule_bn" placeholder="শনিবার থেকে বৃহস্পতিবার বিকাল ৩টা থেকে রাত ৯টা (শুক্রবার বন্ধ)"></div>
                                    </div>

                                    <div class="wp-field-pair">
                                        <div><label>Chamber Address</label><input type="text" name="chamber_address" placeholder="Chamber address or room/floor details"></div>
                                        <div><label>Chamber Address Bangla</label><input type="text" name="chamber_address_bn" placeholder="চেম্বারের ঠিকানা বা রুম/ফ্লোরের তথ্য"></div>
                                    </div>
                                </div>

                                <div class="wp-days-row">
                                    ${dayOptions}
                                    <label class="wp-day-pill wp-day-pill-closed"><input type="checkbox" name="is_closed" value="1"><span class="wp-day-check"></span><span class="wp-day-text">Closed / বন্ধ</span></label>
                                </div>

                                <div class="lite-edit-form-actions">
                                    <button type="submit" class="lite-btn lite-btn-primary">Save Chamber</button>
                                    <button type="button" class="lite-btn lite-btn-light" data-unsaved-chamber-remove="${editRowId}">Remove</button>
                                </div>
                            </form>
                        </td>
                    </tr>`;
            }


            function bindChamberCollapse(root = document) {
                if (!root) return;

                root.querySelectorAll('.lite-chamber-entry').forEach(function(row) {
                    if (row.dataset.rowToggleBound === '1') return;
                    row.dataset.rowToggleBound = '1';

                    let chamberRowClickTimer = null;
                    let justOpenedUntil = 0;

                    function getRowEditTarget() {
                        const toggle = row.querySelector('[data-chamber-edit-toggle]');
                        const targetId = toggle ? toggle.getAttribute('data-chamber-edit-toggle') : '';
                        return targetId ? document.getElementById(targetId) : null;
                    }

                    function closeChamberRow(editRow) {
                        if (!editRow) return;

                        editRow.classList.remove('is-open');
                        row.classList.remove('is-editing');
                    }

                    function openChamberRow(editRow) {
                        if (!editRow) return;

                        closeOtherChamberRows(editRow);
                        editRow.classList.add('is-open');
                        row.classList.add('is-editing');

                        /*
                         * Double/triple click on a closed row should open it.
                         * The extra click generated by a triple-click should not instantly close it.
                         */
                        justOpenedUntil = Date.now() + 450;
                    }

                    function closeOtherChamberRows(currentEditRow) {
                        document.querySelectorAll('.lite-chamber-edit-row.is-open').forEach(function(openRow) {
                            if (openRow !== currentEditRow) {
                                openRow.classList.remove('is-open');
                                const previousEntry = openRow.previousElementSibling;
                                if (previousEntry && previousEntry.classList.contains('lite-chamber-entry')) {
                                    previousEntry.classList.remove('is-editing');
                                }
                            }
                        });
                    }

                    row.addEventListener('click', function(event) {
                        const ignored = event.target.closest('a, button, input, select, textarea, label, form, [data-chamber-menu-wrap], .lite-chamber-menu');

                        if (ignored) {
                            return;
                        }

                        clearTimeout(chamberRowClickTimer);

                        chamberRowClickTimer = setTimeout(function() {
                            const editRow = getRowEditTarget();

                            if (!editRow || !editRow.classList.contains('is-open')) {
                                return;
                            }

                            if (Date.now() < justOpenedUntil) {
                                return;
                            }

                            closeChamberRow(editRow);
                        }, 180);
                    });

                    row.addEventListener('dblclick', function(event) {
                        const ignored = event.target.closest('a, button, input, select, textarea, label, form, [data-chamber-menu-wrap], .lite-chamber-menu');

                        if (ignored) {
                            return;
                        }

                        event.preventDefault();
                        clearTimeout(chamberRowClickTimer);

                        const editRow = getRowEditTarget();

                        if (!editRow) {
                            return;
                        }

                        if (editRow.classList.contains('is-open')) {
                            if (Date.now() < justOpenedUntil) {
                                return;
                            }

                            closeChamberRow(editRow);
                            return;
                        }

                        openChamberRow(editRow);
                    });
                });

                root.querySelectorAll('[data-chamber-edit-toggle]').forEach(function(button) {
                    if (button.dataset.editBound === '1') return;
                    button.dataset.editBound = '1';

                    button.addEventListener('click', function(event) {
                        event.preventDefault();
                        const targetId = button.getAttribute('data-chamber-edit-toggle');
                        const editRow = targetId ? document.getElementById(targetId) : null;
                        const entryRow = button.closest('.lite-chamber-entry');

                        if (!editRow) {
                            return;
                        }

                        const willOpen = !editRow.classList.contains('is-open');

                        document.querySelectorAll('.lite-chamber-edit-row.is-open').forEach(function(openRow) {
                            if (openRow !== editRow) {
                                openRow.classList.remove('is-open');
                                const previousEntry = openRow.previousElementSibling;
                                if (previousEntry && previousEntry.classList.contains('lite-chamber-entry')) {
                                    previousEntry.classList.remove('is-editing');
                                }
                            }
                        });

                        editRow.classList.toggle('is-open', willOpen);

                        if (entryRow) {
                            entryRow.classList.toggle('is-editing', willOpen);
                        }
                    });
                });

                root.querySelectorAll('[data-chamber-edit-cancel]').forEach(function(button) {
                    if (button.dataset.cancelBound === '1') return;
                    button.dataset.cancelBound = '1';

                    button.addEventListener('click', function(event) {
                        event.preventDefault();
                        const editRow = button.closest('.lite-chamber-edit-row');
                        if (!editRow) return;

                        editRow.classList.remove('is-open');
                        const entryRow = editRow.previousElementSibling;
                        if (entryRow && entryRow.classList.contains('lite-chamber-entry')) {
                            entryRow.classList.remove('is-editing');
                        }
                    });
                });

                root.querySelectorAll('[data-unsaved-chamber-remove]').forEach(function(button) {
                    if (button.dataset.removeBound === '1') return;
                    button.dataset.removeBound = '1';

                    button.addEventListener('click', function(event) {
                        event.preventDefault();
                        const editRow = button.closest('.lite-chamber-edit-row');
                        const entryRow = editRow ? editRow.previousElementSibling : null;

                        if (editRow) editRow.remove();
                        if (entryRow && entryRow.classList.contains('lite-chamber-entry')) entryRow.remove();

                        renumberChamberRows();
                    });
                });
            }

            if (chamberDivision) {
                chamberDivision.addEventListener('change', function() {
                    clearHospitalSelection();
                    loadDoctorChamberDistricts(this.value);
                });
            }

            if (chamberDistrict) {
                chamberDistrict.addEventListener('change', function() {
                    clearHospitalSelection();
                    loadDoctorChamberThanas(this.value);
                });
            }

            if (chamberThana) {
                chamberThana.addEventListener('change', function() {
                    clearHospitalSelection();
                    scheduleHospitalSearch(true);
                    updateDoctorChamberPreview();
                });
            }

            if (hospitalSearch) {
                hospitalSearch.addEventListener('focus', function() {
                    openHospitalDropdown();
                    scheduleHospitalSearch(true);
                });

                hospitalSearch.addEventListener('click', function() {
                    openHospitalDropdown();
                    scheduleHospitalSearch(true);
                });

                hospitalSearch.addEventListener('input', function() {
                    if (hospitalHidden) hospitalHidden.value = '';
                    if (hospitalViewUrl) hospitalViewUrl.value = '';
                    if (hospitalSearch) hospitalSearch.dataset.location = '';
                    openHospitalDropdown();
                    scheduleHospitalSearch(false);
                    updateDoctorChamberPreview();
                });
            }

            document.addEventListener('click', function(event) {
                if (!hospitalDropdown) return;
                if (!hospitalDropdown.contains(event.target)) closeHospitalDropdown();
            });

            if (addChamberAjaxForm) {
                addChamberAjaxForm.addEventListener('submit', function(event) {
                    event.preventDefault();

                    if (!hospitalHidden || !hospitalHidden.value) {
                        showDoctorFormMessage('error', 'Please select a hospital.');
                        return;
                    }

                    const hospitalId = hospitalHidden.value;
                    const hospitalName = hospitalSearch ? hospitalSearch.value.trim() : 'Hospital / Chamber';
                    const viewUrl = hospitalViewUrl ? hospitalViewUrl.value : '';
                    const hospitalLocation = hospitalSearch && hospitalSearch.dataset.location ? hospitalSearch.dataset.location : '';

                    const existingRow = getExistingChamberEntry(hospitalId);

                    if (existingRow) {
                        const ok = window.confirm(hospitalName + ' is already added for this doctor. Click OK to edit the existing chamber.');

                        if (ok) {
                            openExistingChamberEdit(existingRow);
                            showDoctorFormMessage('success', 'Existing chamber edit form opened. Update the information and click Update Chamber.');
                        } else {
                            showDoctorFormMessage('error', 'This hospital is already added. No new chamber was created.');
                        }

                        return;
                    }

                    if (noChamberMessage) noChamberMessage.remove();

                    if (doctorChamberList) {
                        const nextSortOrder = getNextChamberSortOrder();
                        doctorChamberList.insertAdjacentHTML('beforeend', buildUnsavedChamberCard(hospitalId, hospitalName, viewUrl, nextSortOrder, hospitalLocation));
                        bindChamberCollapse(doctorChamberList);
                        renumberChamberRows();
                    }

                    clearHospitalSelection();
                    renderHospitalList([], 'Type hospital name or select location to search');
                    updateDoctorChamberPreview();

                    showDoctorFormMessage('success', 'Hospital row added. Fill all fields and click Save Chamber.');
                });
            }


            document.addEventListener('click', function(event) {
                const toggle = event.target.closest('[data-chamber-menu-toggle]');

                document.querySelectorAll('[data-chamber-menu-wrap].is-open').forEach(function(openMenu) {
                    if (!toggle || openMenu !== toggle.closest('[data-chamber-menu-wrap]')) {
                        openMenu.classList.remove('is-open');
                    }
                });

                if (toggle) {
                    event.preventDefault();
                    event.stopPropagation();

                    const wrap = toggle.closest('[data-chamber-menu-wrap]');

                    if (wrap) {
                        wrap.classList.toggle('is-open');
                    }

                    return;
                }

                if (!event.target.closest('[data-chamber-menu-wrap]')) {
                    document.querySelectorAll('[data-chamber-menu-wrap].is-open').forEach(function(openMenu) {
                        openMenu.classList.remove('is-open');
                    });
                }
            });

            bindChamberCollapse(document);
            renumberChamberRows();
            document.querySelectorAll('.lite-chamber-edit-row.is-open').forEach(function(openRow) {
                const entryRow = openRow.previousElementSibling;
                if (entryRow && entryRow.classList.contains('lite-chamber-entry')) {
                    entryRow.classList.add('is-editing');
                }
            });
            renderHospitalList([], 'Type hospital name or select location to search');
            updateDoctorChamberPreview();
        })();
        </script>
        <?php
    }
}


if (!function_exists('doctor_hospitals_render')) {
    function doctor_hospitals_render(array $context = []): void
    {
        doctor_hospitals_availability_render($context);
    }
}

if (!function_exists('doctor_hospitals_availability_section_render')) {
    function doctor_hospitals_availability_section_render(array $context = []): void
    {
        doctor_hospitals_availability_render($context);
    }
}

if (!empty($GLOBALS['doctor_form_auto_render_sections'])) {
    doctor_hospitals_availability_render($GLOBALS['doctor_form_context'] ?? []);
}
