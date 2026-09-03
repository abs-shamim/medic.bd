<?php 
require_once __DIR__ . '/includes/header.php';

$counts = get_setting_counts();

$total_profiles = (int)($counts['doctors'] ?? 0) + (int)($counts['hospitals'] ?? 0);
$total_directory = (int)($counts['specialties'] ?? 0) + (int)($counts['locations'] ?? 0);
$total_activity = (int)($counts['reviews'] ?? 0);
?>

<style>
    .dashboard-page {
        color: #24292f;
    }

    .dashboard-hero {
        background: #ffffff;
        border: 1px solid #d0d7de;
        border-radius: 10px;
        padding: 22px;
        margin-bottom: 18px;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
    }

    .dashboard-hero-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
    }

    .dashboard-title-wrap h1 {
        margin: 0 0 8px;
        color: #24292f;
        font-size: 26px;
        font-weight: 700;
        letter-spacing: -0.03em;
    }

    .dashboard-title-wrap p {
        margin: 0;
        color: #57606a;
        font-size: 14px;
        line-height: 1.6;
    }

    .dashboard-hero-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .dash-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 7px 13px;
        border-radius: 6px;
        border: 1px solid rgba(27, 31, 36, 0.15);
        background: #f6f8fa;
        color: #24292f;
        font-size: 13px;
        font-weight: 600;
        line-height: 1;
        text-decoration: none;
        cursor: pointer;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
        transition: .15s ease;
    }

    .dash-btn:hover {
        background: #f3f4f6;
        border-color: rgba(27, 31, 36, 0.25);
        text-decoration: none;
    }

    .dash-btn-primary {
        background: #2da44e;
        color: #ffffff;
        border-color: rgba(27, 31, 36, 0.15);
    }

    .dash-btn-primary:hover {
        background: #1f883d;
        color: #ffffff;
    }

    .dashboard-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(180px, 1fr));
        gap: 12px;
        margin-top: 20px;
    }

    .summary-box {
        border: 1px solid #d8dee4;
        background: #f6f8fa;
        border-radius: 8px;
        padding: 14px;
    }

    .summary-box strong {
        display: block;
        color: #24292f;
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .summary-box span {
        color: #57606a;
        font-size: 13px;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(180px, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .dash-stat-card {
        position: relative;
        background: #ffffff;
        border: 1px solid #d0d7de;
        border-radius: 10px;
        padding: 16px;
        overflow: hidden;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
        transition: .18s ease;
    }

    .dash-stat-card:hover {
        border-color: #8c959f;
        transform: translateY(-1px);
        box-shadow: 0 8px 20px rgba(27, 31, 36, 0.08);
    }

    .dash-stat-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
    }

    .dash-icon {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f6f8fa;
        border: 1px solid #d8dee4;
        color: #57606a;
        font-size: 18px;
        font-weight: 700;
    }

    .dash-stat-card h3 {
        margin: 0;
        color: #24292f;
        font-size: 28px;
        font-weight: 700;
        letter-spacing: -0.03em;
    }

    .dash-stat-card p {
        margin: 0;
        color: #57606a;
        font-size: 14px;
        font-weight: 600;
    }

    .dash-stat-link {
        display: inline-flex;
        margin-top: 14px;
        color: #0969da;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
    }

    .dash-stat-link:hover {
        text-decoration: underline;
    }

    .dashboard-bottom {
        display: grid;
        grid-template-columns: 1.4fr .8fr;
        gap: 16px;
    }

    .dash-panel {
        background: #ffffff;
        border: 1px solid #d0d7de;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
    }

    .dash-panel-header {
        padding: 14px 16px;
        background: #f6f8fa;
        border-bottom: 1px solid #d0d7de;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .dash-panel-header h2 {
        margin: 0;
        color: #24292f;
        font-size: 15px;
        font-weight: 700;
    }

    .dash-panel-header span {
        color: #57606a;
        font-size: 13px;
    }

    .dash-panel-body {
        padding: 16px;
    }

    .quick-action-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(180px, 1fr));
        gap: 12px;
    }

    .quick-action {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px;
        border: 1px solid #d8dee4;
        border-radius: 8px;
        background: #ffffff;
        text-decoration: none;
        transition: .15s ease;
    }

    .quick-action:hover {
        background: #f6f8fa;
        border-color: #8c959f;
        text-decoration: none;
    }

    .quick-action-icon {
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        background: #f6f8fa;
        border: 1px solid #d8dee4;
        color: #57606a;
        font-size: 16px;
    }

    .quick-action strong {
        display: block;
        color: #24292f;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .quick-action small {
        display: block;
        color: #57606a;
        font-size: 12px;
        line-height: 1.5;
    }

    .system-list {
        display: grid;
        gap: 10px;
    }

    .system-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px;
        border: 1px solid #d8dee4;
        border-radius: 8px;
        background: #ffffff;
    }

    .system-item span {
        color: #57606a;
        font-size: 13px;
    }

    .system-item strong {
        color: #24292f;
        font-size: 13px;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 9px;
        border-radius: 999px;
        background: #dafbe1;
        color: #1a7f37;
        border: 1px solid rgba(26, 127, 55, 0.25);
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .status-pill::before {
        content: "";
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #1a7f37;
    }

    @media(max-width: 1100px) {
        .dashboard-grid,
        .dashboard-summary {
            grid-template-columns: repeat(2, 1fr);
        }

        .dashboard-bottom {
            grid-template-columns: 1fr;
        }
    }

    @media(max-width: 700px) {
        .dashboard-hero {
            padding: 16px;
        }

        .dashboard-title-wrap h1 {
            font-size: 22px;
        }

        .dashboard-grid,
        .dashboard-summary,
        .quick-action-grid {
            grid-template-columns: 1fr;
        }

        .dashboard-hero-actions {
            width: 100%;
        }

        .dash-btn {
            width: 100%;
        }
    }
</style>

<div class="dashboard-page">

    <div class="dashboard-hero">
        <div class="dashboard-hero-top">
            <div class="dashboard-title-wrap">
                <h1>Dashboard</h1>
                <p>Manage doctors, hospitals, specialties, locations, reviews and appointments from one clean admin area.</p>
            </div>

            <div class="dashboard-hero-actions">
                <a href="doctor-form.php" class="dash-btn dash-btn-primary">Add Doctor</a>
                <a href="hospital-form.php" class="dash-btn">Add Hospital</a>
            </div>
        </div>

        <div class="dashboard-summary">
            <div class="summary-box">
                <strong><?= number_format($total_profiles) ?></strong>
                <span>Total Profiles</span>
            </div>

            <div class="summary-box">
                <strong><?= number_format($total_directory) ?></strong>
                <span>Directory Items</span>
            </div>

            <div class="summary-box">
                <strong><?= number_format($total_activity) ?></strong>
                <span>User Activities</span>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="dash-stat-card">
            <div class="dash-stat-head">
                <div>
                    <h3><?= number_format($counts['doctors']) ?></h3>
                    <p>Doctors</p>
                </div>
                <div class="dash-icon">D</div>
            </div>
            <a href="doctors.php" class="dash-stat-link">View doctors</a>
        </div>

        <div class="dash-stat-card">
            <div class="dash-stat-head">
                <div>
                    <h3><?= number_format($counts['hospitals']) ?></h3>
                    <p>Hospitals</p>
                </div>
                <div class="dash-icon">H</div>
            </div>
            <a href="hospitals.php" class="dash-stat-link">View hospitals</a>
        </div>

        <div class="dash-stat-card">
            <div class="dash-stat-head">
                <div>
                    <h3><?= number_format($counts['specialties']) ?></h3>
                    <p>Specialties</p>
                </div>
                <div class="dash-icon">S</div>
            </div>
            <a href="specialties.php" class="dash-stat-link">Manage specialties</a>
        </div>

        <div class="dash-stat-card">
            <div class="dash-stat-head">
                <div>
                    <h3><?= number_format($counts['locations']) ?></h3>
                    <p>Locations</p>
                </div>
                <div class="dash-icon">L</div>
            </div>
            <a href="locations.php" class="dash-stat-link">Manage locations</a>
        </div>

        <div class="dash-stat-card">
            <div class="dash-stat-head">
                <div>
                    <h3><?= number_format($counts['reviews']) ?></h3>
                    <p>Reviews</p>
                </div>
                <div class="dash-icon">R</div>
            </div>
            <a href="reviews.php" class="dash-stat-link">Manage reviews</a>
        </div>
    </div>

    <div class="dashboard-bottom">
        <div class="dash-panel">
            <div class="dash-panel-header">
                <h2>Quick Actions</h2>
                <span>Common admin tasks</span>
            </div>

            <div class="dash-panel-body">
                <div class="quick-action-grid">
                    <a href="doctor-form.php" class="quick-action">
                        <span class="quick-action-icon">+</span>
                        <span>
                            <strong>Add Doctor</strong>
                            <small>Create a new doctor profile with specialty, hospital and contact details.</small>
                        </span>
                    </a>

                    <a href="hospital-form.php" class="quick-action">
                        <span class="quick-action-icon">+</span>
                        <span>
                            <strong>Add Hospital</strong>
                            <small>Add hospital information, address, contact and related data.</small>
                        </span>
                    </a>

                    <a href="specialties.php" class="quick-action">
                        <span class="quick-action-icon">#</span>
                        <span>
                            <strong>Manage Specialties</strong>
                            <small>Organize doctor specialties for better search and filtering.</small>
                        </span>
                    </a>

                    <a href="locations.php" class="quick-action">
                        <span class="quick-action-icon">⌖</span>
                        <span>
                            <strong>Manage Locations</strong>
                            <small>Control location data used across doctor and hospital profiles.</small>
                        </span>
                    </a>

                    <a href="reviews.php" class="quick-action">
                        <span class="quick-action-icon">★</span>
                        <span>
                            <strong>Manage Reviews</strong>
                            <small>Review user feedback and keep profile quality trusted.</small>
                        </span>
                    </a>

                </div>
            </div>
        </div>

        <div class="dash-panel">
            <div class="dash-panel-header">
                <h2>System Overview</h2>
                <span>Current status</span>
            </div>

            <div class="dash-panel-body">
                <div class="system-list">
                    <div class="system-item">
                        <span>Profile Directory</span>
                        <strong class="status-pill">Active</strong>
                    </div>

                    <div class="system-item">
                        <span>Doctors</span>
                        <strong><?= number_format($counts['doctors']) ?></strong>
                    </div>

                    <div class="system-item">
                        <span>Hospitals</span>
                        <strong><?= number_format($counts['hospitals']) ?></strong>
                    </div>

                    <div class="system-item">
                        <span>Reviews</span>
                        <strong><?= number_format($counts['reviews']) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>