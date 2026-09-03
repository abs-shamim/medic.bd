<?php
require_once __DIR__ . '/includes/functions.php';

global $pdo;

function ensure_contacts_table(): void
{
    global $pdo;

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `contacts` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(190) NOT NULL,
                `email` VARCHAR(190) NOT NULL,
                `subject` VARCHAR(190) NULL,
                `message` TEXT NULL,
                `status` ENUM('unread','read') NOT NULL DEFAULT 'unread',
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if (!column_exists('contacts', 'status')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `status` ENUM('unread','read') NOT NULL DEFAULT 'unread' AFTER `message`");
        }
    } catch (Throwable $e) {
        // If the hosting database user has no CREATE/ALTER permission, the
        // page still loads and the insert below is attempted as-is.
    }
}

ensure_contacts_table();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("
        INSERT INTO contacts (name, email, subject, message, status, created_at)
        VALUES (:name, :email, :subject, :message, 'unread', NOW())
    ");

    $stmt->execute([
        ':name' => $_POST['name'] ?? '',
        ':email' => $_POST['email'] ?? '',
        ':subject' => $_POST['subject'] ?? '',
        ':message' => $_POST['message'] ?? '',
    ]);

    flash('success', 'Your message has been sent successfully.');
    redirect(site_url('contact'));
}

$page_title = 'Contact | MediCare';
$meta_description = 'Contact MediCare healthcare directory.';

include __DIR__ . '/includes/header.php';
?>

<style>
  .medic-contact-page {
    background: #f6f8fa;
    padding: 24px 0 56px;
    color: #24292f;
  }

  .medic-breadcrumb {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 7px;
    margin: 0 0 16px;
    color: #57606a;
    font-size: 14px;
  }

  .medic-breadcrumb a {
    color: #0969da;
    text-decoration: none;
    font-weight: 600;
  }

  .medic-breadcrumb a:hover {
    text-decoration: underline;
  }

  .medic-breadcrumb span {
    color: #8c959f;
  }

  .medic-contact-head {
    overflow: hidden;
    margin-bottom: 16px;
    border: 1px solid #d0d7de;
    border-radius: 6px;
    background: #ffffff;
  }

  .medic-contact-head-top {
    padding: 20px;
    border-bottom: 1px solid #d8dee4;
    background: linear-gradient(180deg, #ffffff, #f6f8fa);
  }

  .medic-contact-head-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
  }

  .medic-contact-head h1 {
    margin: 0 0 6px;
    color: #24292f;
    font-size: 28px;
    line-height: 1.2;
    letter-spacing: -0.03em;
    font-weight: 800;
  }

  .medic-contact-head p {
    margin: 0;
    color: #57606a;
    font-size: 14px;
    line-height: 1.6;
  }

  .medic-support-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 30px;
    padding: 5px 10px;
    border-radius: 999px;
    background: #dafbe1;
    color: #1a7f37;
    border: 1px solid rgba(26, 127, 55, 0.20);
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
  }

  .medic-contact-head-bottom {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 14px;
    padding: 12px 20px;
    background: #ffffff;
    color: #57606a;
    font-size: 13px;
  }

  .medic-contact-head-bottom strong {
    color: #24292f;
    font-weight: 700;
  }

  .medic-contact-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 16px;
    align-items: start;
  }

  .medic-panel {
    overflow: hidden;
    border: 1px solid #d0d7de;
    border-radius: 6px;
    background: #ffffff;
  }

  .medic-panel-head {
    padding: 14px 16px;
    border-bottom: 1px solid #d8dee4;
    background: #f6f8fa;
  }

  .medic-panel-head h2 {
    margin: 0;
    color: #24292f;
    font-size: 16px;
    line-height: 1.35;
    font-weight: 800;
  }

  .medic-panel-head p {
    margin: 4px 0 0;
    color: #57606a;
    font-size: 13px;
    line-height: 1.5;
  }

  .medic-form {
    display: grid;
    gap: 12px;
    padding: 16px;
  }

  .medic-field {
    display: grid;
    gap: 6px;
  }

  .medic-field label {
    color: #24292f;
    font-size: 13px;
    font-weight: 700;
  }

  .medic-field input,
  .medic-field textarea {
    width: 100%;
    border: 1px solid #d0d7de;
    border-radius: 6px;
    background: #f6f8fa;
    color: #24292f;
    font-family: inherit;
    font-size: 14px;
    outline: none;
  }

  .medic-field input {
    height: 40px;
    padding: 0 12px;
  }

  .medic-field textarea {
    min-height: 150px;
    padding: 11px 12px;
    line-height: 1.6;
    resize: vertical;
  }

  .medic-field input:focus,
  .medic-field textarea:focus {
    background: #ffffff;
    border-color: #0969da;
    box-shadow: 0 0 0 3px rgba(9, 105, 218, 0.12);
  }

  .medic-submit-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 0 16px;
    border-radius: 6px;
    border: 1px solid rgba(27, 31, 36, 0.15);
    background: #2da44e;
    color: #ffffff;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    white-space: nowrap;
  }

  .medic-submit-btn:hover {
    background: #1f883d;
    color: #ffffff;
  }

  .medic-info-list {
    display: grid;
    gap: 0;
  }

  .medic-info-item {
    display: grid;
    grid-template-columns: 36px minmax(0, 1fr);
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid #d8dee4;
  }

  .medic-info-item:last-child {
    border-bottom: 0;
  }

  .medic-info-icon {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #ddf4ff;
    color: #0969da;
    border: 1px solid rgba(9, 105, 218, 0.14);
    font-size: 16px;
    font-weight: 800;
  }

  .medic-info-item h3 {
    margin: 0 0 4px;
    color: #24292f;
    font-size: 14px;
    line-height: 1.35;
    font-weight: 800;
  }

  .medic-info-item p,
  .medic-info-item a {
    margin: 0;
    color: #57606a;
    font-size: 13px;
    line-height: 1.5;
    text-decoration: none;
  }

  .medic-info-item a:hover {
    color: #0969da;
    text-decoration: underline;
  }

  .medic-map-box {
    margin: 16px;
    min-height: 170px;
    border-radius: 6px;
    border: 1px dashed #d0d7de;
    background:
      linear-gradient(135deg, rgba(9, 105, 218, 0.08), rgba(45, 164, 78, 0.08)),
      #f6f8fa;
    display: grid;
    place-items: center;
    color: #57606a;
    font-size: 13px;
    font-weight: 700;
  }

  @media (max-width: 900px) {
    .medic-contact-grid {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 760px) {
    .medic-contact-page {
      padding-top: 18px;
    }

    .medic-contact-head-row {
      flex-direction: column;
    }

    .medic-contact-head-top {
      padding: 16px;
    }

    .medic-contact-head-bottom {
      padding: 12px 16px;
    }

    .medic-contact-head h1 {
      font-size: 26px;
    }

    .medic-support-badge {
      width: 100%;
    }

    .medic-form {
      padding: 14px;
    }

    .medic-submit-btn {
      width: 100%;
    }
  }

  @media (max-width: 420px) {
    .medic-info-item {
      grid-template-columns: 1fr;
    }
  }
</style>

<main class="medic-contact-page">
  <div class="container">
    <?php show_flash(); ?>

    <nav class="medic-breadcrumb">
      <a href="<?= e(site_url()) ?>">Home</a>
      <span>/</span>
      <a href="<?= e(site_url('contact')) ?>">Contact</a>
    </nav>

    <section class="medic-contact-head">
      <div class="medic-contact-head-top">
        <div class="medic-contact-head-row">
          <div>
            <h1>Contact Us</h1>
            <p>Contact MediCare for doctor, hospital and appointment support.</p>
          </div>

          <div class="medic-support-badge">
            24/7 Support
          </div>
        </div>
      </div>

      <div class="medic-contact-head-bottom">
        <span><strong>Status:</strong> Support available</span>
        <span><strong>Response:</strong> As soon as possible</span>
      </div>
    </section>

    <section class="medic-contact-grid">
      <div class="medic-panel">
        <div class="medic-panel-head">
          <h2>Send Message</h2>
          <p>Write your message and the support team will review it.</p>
        </div>

        <form class="medic-form" method="POST" action="<?= e(site_url('contact')) ?>">
          <div class="medic-field">
            <label for="contactName">Name</label>
            <input id="contactName" type="text" name="name" placeholder="Your name" required>
          </div>

          <div class="medic-field">
            <label for="contactEmail">Email</label>
            <input id="contactEmail" type="email" name="email" placeholder="Email address" required>
          </div>

          <div class="medic-field">
            <label for="contactSubject">Subject</label>
            <input id="contactSubject" type="text" name="subject" placeholder="Subject" required>
          </div>

          <div class="medic-field">
            <label for="contactMessage">Message</label>
            <textarea id="contactMessage" name="message" placeholder="Message" required></textarea>
          </div>

          <button class="medic-submit-btn" type="submit">
            Send Message
          </button>
        </form>
      </div>

      <aside class="medic-panel">
        <div class="medic-panel-head">
          <h2>Contact Info</h2>
          <p>Use the details below to reach the MediCare team.</p>
        </div>

        <div class="medic-info-list">
          <div class="medic-info-item">
            <div class="medic-info-icon">⌂</div>
            <div>
              <h3>Location</h3>
              <p>Dhaka, Bangladesh</p>
            </div>
          </div>

          <div class="medic-info-item">
            <div class="medic-info-icon">☎</div>
            <div>
              <h3>Phone</h3>
              <a href="tel:+8801234567890">+880 1234 567 890</a>
            </div>
          </div>

          <div class="medic-info-item">
            <div class="medic-info-icon">@</div>
            <div>
              <h3>Email</h3>
              <a href="mailto:info@example.com">info@example.com</a>
            </div>
          </div>
        </div>

        <div class="medic-map-box">
          Map Area
        </div>
      </aside>
    </section>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>