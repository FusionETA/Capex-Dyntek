<?php
/**
 * Manage Access view.
 * @var array<int,array<string,mixed>> $rows   current access list
 * @var array<int,string> $addable            users not yet granted (id => label)
 * @var array<string,string> $labels          role token => label
 * @var int $meId
 * @var array{ok:bool,message:string}|null $flash
 * @var string $memberId
 * @var array{id:int,role:string,token:string} $user
 */
declare(strict_types=1);
$idx = capex_base() . '/index.php';
$roleSelect = function (string $name, string $current) use ($labels): string {
    $h = '<select name="' . e($name) . '" class="t-in" style="width:auto;text-align:left">';
    foreach ($labels as $token => $label) {
        $h .= '<option value="' . e($token) . '"' . ($current === $token ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $h . '</select>';
};
?>
<h1>Manage Access</h1>
<p class="muted">Grant, change or remove who can use the Capex app. Anyone not listed here has no access.<details class="rolehelp">
    <summary title="What the roles mean">i</summary>
    <div class="rolehelp-pop">
        <h4>What each role can do</h4>
        <ul>
            <li><b>Viewer</b> — opens the app; reads dashboard &amp; targets only.</li>
            <li><b>Requester</b> — Tier 0–2 (MG–MS); can submit capex requests.</li>
            <li><b>HOD</b> — Requester + approves up to <b>S$50k</b>.</li>
            <li><b>Regional Finance</b> — approves up to <b>S$250k</b> + edits sales targets.</li>
            <li><b>Country MD</b> — approves up to <b>S$1m</b>.</li>
            <li><b>Group CFO</b> — approves <b>any</b> amount + manages access.</li>
            <li><b>System Admin</b> — manages access only; no submit/approve.</li>
        </ul>
    </div>
</details></p>

<?php if ($flash !== null): ?>
    <div class="<?= $flash['ok'] ? 'notice' : 'alert' ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<section class="card">
    <h2>People with access</h2>
    <table class="grid">
        <thead><tr><th>User</th><th>Role</th><th>Change</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong><?= e($r['name']) ?></strong> <span class="chip">#<?= e($r['id']) ?></span><?= $r['id'] === $meId ? ' <span class="chip">you</span>' : '' ?></td>
                <td><span class="chip"><?= e($labels[$r['role']] ?? $r['role']) ?></span></td>
                <td>
                    <form method="post" action="<?= e($idx) ?>?screen=access" style="display:flex;gap:6px;align-items:center">
                        <input type="hidden" name="member_id" value="<?= e($memberId) ?>">
                        <input type="hidden" name="utok" value="<?= e($user['token']) ?>">
                        <input type="hidden" name="action" value="set">
                        <input type="hidden" name="user_id" value="<?= e($r['id']) ?>">
                        <?= $roleSelect('role', $r['role']) ?>
                        <button type="submit" class="btn-primary btn-sm">Save</button>
                    </form>
                </td>
                <td>
                    <form method="post" action="<?= e($idx) ?>?screen=access" onsubmit="return confirm('Remove access for <?= e(addslashes($r['name'])) ?>?')" style="display:inline">
                        <input type="hidden" name="member_id" value="<?= e($memberId) ?>">
                        <input type="hidden" name="utok" value="<?= e($user['token']) ?>">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="user_id" value="<?= e($r['id']) ?>">
                        <button type="submit" class="btn-reject btn-sm">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="card">
    <h2>Grant access to someone</h2>
    <?php if (!$addable): ?>
        <p class="muted">Everyone in the portal already has an assigned role.</p>
    <?php else: ?>
    <form method="post" action="<?= e($idx) ?>?screen=access" class="capex-form" style="max-width:560px">
        <input type="hidden" name="member_id" value="<?= e($memberId) ?>">
        <input type="hidden" name="utok" value="<?= e($user['token']) ?>">
        <input type="hidden" name="action" value="set">
        <div class="form-row">
            <label>User
                <select name="user_id" required>
                    <option value="">— pick a person —</option>
                    <?php foreach ($addable as $uid => $label): ?>
                        <option value="<?= e($uid) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Role
                <select name="role" required>
                    <?php foreach ($labels as $token => $label): ?>
                        <option value="<?= e($token) ?>"<?= $token === 'REQUESTER' ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="form-actions"><button type="submit" class="btn-primary">Grant access</button></div>
    </form>
    <?php endif; ?>
</section>
