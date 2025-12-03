<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}
include 'db_config.php';

// fetch roles from DB, fallback to default list
$roles = [];
try {
    $stmt = $pdo->query("SELECT id, role_name FROM roles ORDER BY role_name");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // ignore - fallback below
}
if (empty($roles)) {
    $roles = [
        ['id' => 1, 'role_name' => 'admin'],
        ['id' => 2, 'role_name' => 'users'],
        ['id' => 3, 'role_name' => 'staff'],
        ['id' => 4, 'role_name' => 'student'],
    ];
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Add User</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 py-10">
  <div class="max-w-2xl mx-auto bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-4">Create User</h2>

    <form id="addUserForm" class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Username</label>
          <input name="username" id="username" required class="mt-1 block w-full border rounded px-3 py-2" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Email</label>
          <input name="email" id="email" type="email" required class="mt-1 block w-full border rounded px-3 py-2" />
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Password</label>
          <input name="password" id="password" type="password" required class="mt-1 block w-full border rounded px-3 py-2" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Role</label>
          <select name="role_id" id="role_id" required class="mt-1 block w-full border rounded px-3 py-2">
            <option value="">-- Select role --</option>
            <?php foreach ($roles as $r): ?>
              <option value="<?= htmlspecialchars($r['id']) ?>"><?= htmlspecialchars(ucfirst($r['role_name'])) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="flex justify-end gap-2">
        <a href="dashboard.php" class="px-4 py-2 border rounded text-gray-700">Cancel</a>
        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded">Create user</button>
      </div>

      <div id="formMessage" class="mt-3 text-sm"></div>
    </form>
  </div>

<script>
document.getElementById('addUserForm').addEventListener('submit', async function(e){
  e.preventDefault();
  const form = e.target;
  const data = new FormData(form);

  const btn = form.querySelector('button[type="submit"]');
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = 'Saving...';

  try {
    const res = await fetch('process_add_user.php', { method: 'POST', body: data });
    const json = await res.json();
    const msg = document.getElementById('formMessage');
    if (json.success) {
      msg.className = 'mt-3 text-sm text-green-700';
      msg.textContent = json.message || 'User created';
      form.reset();
    } else {
      msg.className = 'mt-3 text-sm text-red-700';
      msg.textContent = json.message || 'Error';
    }
  } catch (err) {
    document.getElementById('formMessage').className = 'mt-3 text-sm text-red-700';
    document.getElementById('formMessage').textContent = 'Request failed: ' + err.message;
  } finally {
    btn.disabled = false;
    btn.innerHTML = orig;
  }
});
</script>
</body>
</html>