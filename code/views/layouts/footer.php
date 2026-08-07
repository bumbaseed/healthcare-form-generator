</main>

<footer class="footer">
    <div class="footer-content">
        <p>&copy; <?php echo date('Y'); ?> <?php echo escape($appConfig['app_name']); ?> - Version
            <?php echo escape($appConfig['version']); ?></p>
        <p class="footer-note">Healthcare IT Portal for Form Generation and Case Management</p>
    </div>
</footer>
</div>

<!-- JavaScript -->
<script src="/js/main.js"></script>
<?php if (isset($extraJS)): ?>
    <?php foreach ($extraJS as $js): ?>
        <script src="<?php echo $js; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>

</html>