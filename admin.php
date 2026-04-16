<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

load_language();

$user = current_user();
if (!$user['is_admin']) { header('Location: index.php'); exit; }

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();

    if ($_POST['action'] === 'invite') {
        $color = $_POST['color'] ?? '#c9a84c';
        $code  = create_invite_code($color);
        $success = sprintf(t('admin_ok_invite'), $code);
    }

    if ($_POST['action'] === 'delete') {
        $del_id = (int)$_POST['del_id'];
        if ($del_id && $del_id != $user['id']) {
            db()->prepare("DELETE FROM users WHERE id = ?")->execute([$del_id]);
            $success = t('admin_ok_deleted');
        }
    }

    if ($_POST['action'] === 'deactivate') {
        $uid = (int)$_POST['uid'];
        if ($uid && $uid != $user['id']) {
            deactivate_user($uid);
            $success = t('admin_ok_deact');
        }
    }

    if ($_POST['action'] === 'activate') {
        $uid = (int)$_POST['uid'];
        activate_user($uid);
        $success = t('admin_ok_act');
    }

    if ($_POST['action'] === 'reset_pass') {
        $uid  = (int)$_POST['uid'];
        $code = create_reset_code($uid);
        $success = sprintf(t('admin_ok_reset'), $code);
    }

    if ($_POST['action'] === 'delete_invite') {
        $inv_id = (int)$_POST['inv_id'];
        db()->prepare("DELETE FROM invite_codes WHERE id=?")->execute([$inv_id]);
        $success = t('admin_ok_inv_del');
    }
}

$users        = all_users();
$invite_codes = get_invite_codes();
$palette      = ['#c9a84c','#4a7fa5','#a5704a','#7a4aa5','#4aa55a','#a54a4a','#4aa5a5','#a5a54a','#d4682a','#6a8a4a'];

require_once __DIR__ . '/layout.php';
layout_head(t('admin_title'), false);
?>

<?php layout_nav($user, ''); ?>

<div class="main">
  <div class="sec-header">
    <div class="sec-title"><?= h(t('admin_title')) ?> <small><?= sprintf(t('admin_users_count'), count($users)) ?></small></div>
    <a href="index.php" class="btn-outline"><?= h(t('back')) ?></a>
  </div>

  <?php if ($error): ?><div class="alert-err"><?= $error ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert-ok"><?= $success ?></div><?php endif; ?>

  <!-- Участники -->
  <div class="fcard" style="max-width:680px; margin-bottom:1.5rem">
    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:1rem"><?= h(t('admin_members')) ?></div>
    <table class="admin-table">
      <thead>
        <tr>
          <th><?= h(t('admin_col_name')) ?></th>
          <th><?= h(t('admin_col_login')) ?></th>
          <th><?= h(t('admin_col_role')) ?></th>
          <th><?= h(t('admin_col_seen')) ?></th>
          <th><?= h(t('admin_col_actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:0.6rem">
              <div class="avatar" style="background:<?= h($u['color']) ?>;width:28px;height:28px;font-size:0.65rem;opacity:<?= $u['is_active'] ? '1' : '0.4' ?>"><?= h(initials($u['display_name'])) ?></div>
              <div>
                <?= h($u['display_name']) ?>
                <?php if (!$u['is_active']): ?>
                  <span style="font-size:0.65rem;color:var(--text-muted);margin-left:0.3rem"><?= h(t('admin_deactivated')) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td style="color:var(--text-muted);font-size:0.82rem"><?= h($u['username']) ?></td>
          <td>
            <?php if ($u['is_admin']): ?>
              <span style="font-size:0.68rem;background:rgba(201,168,76,.15);border:1px solid rgba(201,168,76,.3);color:var(--gold);padding:1px 6px;border-radius:2px;font-weight:700;letter-spacing:0.07em;text-transform:uppercase"><?= h(t('admin_role_admin')) ?></span>
            <?php else: ?>
              <span style="font-size:0.68rem;color:var(--text-muted)"><?= h(t('admin_role_member')) ?></span>
            <?php endif; ?>
          </td>
          <td style="font-size:0.78rem;color:var(--text-muted)">
            <?= $u['last_seen'] ? date('d.m.Y H:i', strtotime($u['last_seen'])) : '—' ?>
          </td>
          <td>
            <?php if ($u['id'] != $user['id']): ?>
            <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap">
              <form method="POST" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset_pass">
                <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                <button type="submit" class="btn-outline" style="font-size:0.7rem;padding:0.2rem 0.6rem"><?= h(t('admin_btn_reset')) ?></button>
              </form>
              <?php if ($u['is_active']): ?>
              <form method="POST" style="display:inline"
                    onsubmit="return confirm('<?= h(addslashes(sprintf(t('admin_conf_deact'), $u['display_name']))) ?>')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="deactivate">
                <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                <button type="submit" class="btn-outline" style="font-size:0.7rem;padding:0.2rem 0.6rem"><?= h(t('admin_btn_deact')) ?></button>
              </form>
              <?php else: ?>
              <form method="POST" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="activate">
                <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                <button type="submit" class="btn-outline" style="font-size:0.7rem;padding:0.2rem 0.6rem;color:var(--gold);border-color:var(--gold)"><?= h(t('admin_btn_act')) ?></button>
              </form>
              <?php endif; ?>
              <form method="POST" style="display:inline"
                    onsubmit="return confirm('<?= h(addslashes(sprintf(t('admin_conf_del'), $u['display_name']))) ?>')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="del_id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn-danger-sm"><?= h(t('admin_btn_del')) ?></button>
              </form>
            </div>
            <?php else: ?>
              <span style="font-size:0.75rem;color:var(--text-muted)"><?= h(t('admin_its_you')) ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Инвайт-коды -->
  <div class="fcard" style="max-width:480px;margin-bottom:1.5rem">
    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:1.2rem"><?= h(t('admin_invite_sec')) ?></div>

    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="invite">
      <div class="mb-4">
        <label class="form-label"><?= h(t('admin_color_label')) ?></label>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.3rem">
          <?php foreach ($palette as $i => $color): ?>
          <label style="cursor:pointer">
            <input type="radio" name="color" value="<?= $color ?>" style="display:none" <?= $i === 0 ? 'checked' : '' ?>>
            <div style="width:26px;height:26px;border-radius:50%;background:<?= $color ?>;border:2px solid transparent;transition:border-color .15s"
                 onclick="this.style.borderColor='white';document.querySelectorAll('.color-swatch').forEach(s=>s.style.borderColor='transparent');this.style.borderColor='white'"
                 class="color-swatch"></div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="submit" class="btn-gold"><?= h(t('admin_btn_invite')) ?></button>
    </form>

    <?php if (!empty($invite_codes)): ?>
    <div style="margin-top:1.2rem;padding-top:1.2rem;border-top:1px solid var(--border)">
      <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);font-weight:600;margin-bottom:0.7rem"><?= h(t('admin_active_codes')) ?></div>
      <?php foreach ($invite_codes as $inv): ?>
      <div style="display:flex;align-items:center;gap:0.7rem;margin-bottom:0.5rem">
        <div style="width:16px;height:16px;border-radius:50%;background:<?= h($inv['color']) ?>;flex-shrink:0"></div>
        <span style="font-size:1.1rem;font-weight:700;letter-spacing:0.15em;color:var(--gold)"><?= h($inv['code']) ?></span>
        <span style="font-size:0.7rem;color:var(--text-muted)">до <?= date('d.m H:i', strtotime($inv['expires_at'])) ?></span>
        <form method="POST" style="margin-left:auto">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_invite">
          <input type="hidden" name="inv_id" value="<?= $inv['id'] ?>">
          <button type="submit" class="btn-danger-sm" style="padding:0.15rem 0.5rem">✕</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php layout_foot(); ?>
