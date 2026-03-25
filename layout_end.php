    </div><!-- /.page-body -->
  </div><!-- /.main-content -->
</div><!-- /#app -->

<div id="toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmFPVZtHDJhNt3zSEYgasJW5EQo" crossorigin="anonymous"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Supabase JS (for Realtime) -->
<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<script src="/app.js"></script>
<?php if (!empty($inlineScript)): ?>
<script><?= $inlineScript ?></script>
<?php endif; ?>
<?php if (!empty($extraScripts)): ?>
  <?php foreach ($extraScripts as $script): ?>
    <script src="<?= $script ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>
<script>
  // Live clock in topbar
  function updateClock() {
    const el = document.getElementById('topbar-time');
    if (el) el.textContent = new Date().toLocaleTimeString('en-PH', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
  }
  updateClock();
  setInterval(updateClock, 1000);
</script>
</body>
</html>
