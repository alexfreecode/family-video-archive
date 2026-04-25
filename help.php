<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user = current_user();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }
load_language();
$admin = get_site_admin();
$admin_name = $admin ? $admin['display_name'] : t('admin_role_admin');

require_once __DIR__ . '/layout.php';
layout_head(t('help_title'), false);
?>

<?php layout_nav($user, ''); ?>

<div class="main" style="max-width:740px">
  <div class="sec-header">
    <div class="sec-title"><?= h(t('help_title')) ?></div>
    <a href="index.php" class="btn-outline"><?= h(t('back')) ?></a>
  </div>

  <div class="fcard">

    <div class="help-section">
      <p><?= t('help_intro1') ?></p>
      <p><?= t('help_intro2') ?></p>
    </div>

    <div class="help-section">
      <div class="help-h2"><?= h(t('help_h_main')) ?></div>
      <p><?= h(t('help_main1')) ?></p>
      <p><?= t('help_main_all') ?></p>
      <p><?= t('help_main_mine') ?></p>
      <p><?= t('help_main_search') ?></p>
    </div>

    <div class="help-section">
      <div class="help-h2"><?= h(t('help_h_watch')) ?></div>
      <p><?= t('help_watch1') ?></p>
      <p><?= t('help_watch2') ?></p>
    </div>

    <div class="help-section">
      <div class="help-h2"><?= h(t('help_h_add')) ?></div>
      <ol class="help-list">
        <li><?= t('help_add_li1') ?></li>
        <li><?= t('help_add_li2') ?></li>
        <li><?= t('help_add_li3') ?></li>
        <li><?= t('help_add_li4') ?></li>
        <li><?= t('help_add_li5') ?>
          <div class="help-access-list">
            <span class="vbadge pub" style="font-size:0.7rem"><?= h(t('access_all')) ?></span> <?= t('help_add_li5_all') ?><br>
            <span class="vbadge" style="font-size:0.7rem"><?= h(t('access_selected')) ?></span> <?= t('help_add_li5_sel') ?><br>
            <span class="vbadge" style="font-size:0.7rem"><?= h(t('access_only_me')) ?></span> <?= t('help_add_li5_me') ?>
          </div>
        </li>
        <li><?= t('help_add_li6') ?></li>
      </ol>
    </div>

    <div class="help-section">
      <div class="help-h2"><?= h(t('help_h_sort')) ?></div>
      <p><?= t('help_sort1') ?></p>
      <p><?= t('help_sort2') ?></p>
      <p><?= t('help_sort3') ?></p>
      <p><?= t('help_sort4') ?></p>
      <p><?= t('help_sort5') ?></p>
    </div>

    <div class="help-section">
      <div class="help-h2"><?= h(t('help_h_timecode')) ?></div>
      <p><?= t('help_timecode1') ?></p>
    </div>

    <div class="help-section">
      <div class="help-h2"><?= h(t('help_h_parts')) ?></div>
      <p><?= t('help_parts1') ?></p>
      <p><?= t('help_parts2') ?></p>
      <p><?= t('help_parts3') ?></p>
      <p><?= t('help_parts4') ?></p>
    </div>

    <div class="help-section">
      <div class="help-h2"><?= h(t('help_h_edit')) ?></div>
      <p><?= t('help_edit1') ?></p>
      <p><?= t('help_edit2') ?></p>
      <p><?= t('help_edit3') ?></p>
    </div>

    <div class="help-section">
      <div class="help-h2"><?= h(t('help_h_badges')) ?></div>
      <p><?= t('help_badges1') ?></p>
      <div style="display:flex;flex-direction:column;gap:0.5rem;margin-top:0.5rem">
        <div style="display:flex;align-items:center;gap:0.7rem">
          <span class="vbadge pub" style="position:static;font-size:0.7rem;flex-shrink:0"><?= h(t('access_all')) ?></span>
          <span style="font-size:0.85rem;color:var(--text-muted)"><?= t('help_badge_all') ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:0.7rem">
          <span class="vbadge" style="position:static;font-size:0.7rem;flex-shrink:0"><?= h(t('admin_col_name')) ?></span>
          <span style="font-size:0.85rem;color:var(--text-muted)"><?= t('help_badge_names') ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:0.7rem">
          <span class="vbadge" style="position:static;font-size:0.7rem;flex-shrink:0"><?= h(t('access_only_me')) ?></span>
          <span style="font-size:0.85rem;color:var(--text-muted)"><?= t('help_badge_me') ?></span>
        </div>
      </div>
    </div>

    <div class="help-section">
      <div class="help-h2"><?= h(t('help_h_newmember')) ?></div>
      <p><?= t('help_newmember1') ?></p>
    </div>

    <div class="help-section">
      <div class="help-h2"><?= h(t('help_h_profile')) ?></div>
      <p><?= t('help_profile1') ?></p>
    </div>

    <div class="help-section">
      <div class="help-h2"><?= h(t('help_h_events')) ?></div>
      <p><?= t('help_events1') ?></p>
      <p><?= t('help_events2') ?></p>
      <p><?= t('help_events3') ?></p>
      <p><?= t('help_events4') ?></p>
      <p><?= t('help_events5') ?></p>
      <p><?= t('help_events6') ?></p>
      <p><?= t('help_events7') ?></p>
    </div>

    <div class="help-section">
      <div class="help-h2"><?= h(t('help_h_bell')) ?></div>
      <p><?= t('help_bell1') ?></p>
    </div>

    <div class="help-section">
      <div class="help-h2"><?= h(t('help_h_comments')) ?></div>
      <p><?= t('help_comments1') ?></p>
      <p><?= t('help_comments2') ?></p>
    </div>

    <?php if (telegram_enabled()): ?>
    <div class="help-section">
      <div class="help-h2"><?= h(t('help_h_telegram')) ?></div>
      <p><?= t('help_telegram1') ?></p>
      <p><?= t('help_telegram2') ?></p>
      <p><?= t('help_telegram3') ?></p>
    </div>
    <?php endif; ?>

    <div class="help-section" style="border-bottom:none;margin-bottom:0;padding-bottom:0">
      <div class="help-h2"><?= h(t('help_h_password')) ?></div>
      <?php if ($admin): ?>
      <div style="display:flex;align-items:center;gap:0.7rem;margin-bottom:1rem;padding:0.7rem 0.9rem;background:var(--surface2);border:1px solid var(--border);border-radius:3px">
        <div class="avatar" style="background:<?= h($admin['color']) ?>;width:32px;height:32px;font-size:0.7rem;flex-shrink:0"><?= h(initials($admin['display_name'])) ?></div>
        <div>
          <div style="font-size:0.72rem;color:var(--text-muted)"><?= h(t('help_admin_label')) ?></div>
          <div style="font-size:0.9rem;font-weight:600;color:var(--text)"><?= h($admin['display_name']) ?></div>
        </div>
      </div>
      <?php endif; ?>
      <p><?= t('help_password1') ?></p>
      <p style="margin-top:1rem;color:var(--text-muted)"><?= t('help_password2') ?></p>
    </div>

  </div>
</div>

<style>
.help-section {
  padding-bottom: 1.2rem;
  margin-bottom: 1.2rem;
  border-bottom: 1px solid var(--border);
}
.help-h2 {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--gold);
  margin-bottom: 0.6rem;
}
.help-section p {
  font-size: 0.88rem;
  color: var(--text);
  line-height: 1.6;
  margin-bottom: 0.5rem;
}
.help-section p:last-child { margin-bottom: 0; }
.help-list {
  font-size: 0.88rem;
  color: var(--text);
  line-height: 1.6;
  padding-left: 1.3rem;
  margin: 0;
}
.help-list li { margin-bottom: 0.4rem; }
.help-access-list {
  margin-top: 0.4rem;
  margin-left: 0.5rem;
  line-height: 2;
}
</style>

<?php layout_foot(); ?>
