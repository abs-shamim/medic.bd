</main>

<footer class="admin-footer">
  <p>
    © <?= date('Y') ?> <?= e(defined('APP_NAME') ? APP_NAME : 'Admin') ?>. 
    All rights reserved.
  </p>
</footer>

</section>
</div>

<style>
  /*
  |--------------------------------------------------------------------------
  | Sticky Bottom Footer
  |--------------------------------------------------------------------------
  */

  .admin-panel {
    min-height: 100vh !important;
    display: flex !important;
    flex-direction: column !important;
  }

  .admin-main {
    flex: 1 0 auto !important;
    width: 100%;
  }

  .admin-footer {
    flex-shrink: 0;
    margin: auto 24px 24px;
    padding: 16px 20px;
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid #d0d7de;
    box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
    text-align: center;
  }

  .admin-footer p {
    margin: 0;
    color: #57606a;
    font-size: 13px;
    line-height: 1.5;
    font-weight: 500;
  }

  @media (max-width: 760px) {
    .admin-footer {
      margin: auto 14px 14px;
      padding: 14px;
    }

    .admin-footer p {
      font-size: 12px;
    }
  }
</style>

</body>
</html>