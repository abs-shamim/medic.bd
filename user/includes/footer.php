    </div>
</div>

<footer class="user-footer py-3 mt-5">
    <div class="container text-center">
        <p class="mb-0 small">
            &copy; <?php echo date('Y'); ?> User Panel. All rights reserved.
        </p>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- User Panel JS -->
<script src="<?php echo BASE_URL; ?>/user/assets/js/user-panel.js"></script>

<style>
    .user-footer {
        background: var(--up-card, #ffffff);
        border-top: 1px solid var(--up-border, #d0d7de);
        color: var(--up-muted, #57606a);
        transition: background 0.25s ease, border-color 0.25s ease, color 0.25s ease;
    }

    html[data-theme="dark"] .user-footer {
        background: var(--up-card, #161b22);
        border-top-color: var(--up-border, #30363d);
        color: var(--up-muted, #8b949e);
    }

    .user-footer p {
        color: inherit !important;
    }
</style>

</body>
</html>