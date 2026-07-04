<!-- 账号内容 -->
<div class="card" style="max-width:560px; margin:0 auto;">
  <div class="card-title" style="justify-content:center;"><i class="fas fa-user-circle"></i>账号中心</div>
  <div class="user-profile" id="userProfile">
    <div class="avatar-wrap" id="avatarWrap">
      <div class="avatar-placeholder" id="avatarPlaceholder"><i class="fas fa-user"></i></div>
      <img id="avatarImg" style="display:none;" alt="头像">
      <div class="upload-hint"><i class="fas fa-camera"></i></div>
    </div>
    <input type="file" id="avatarInput" accept="image/*">
    <div class="user-email" id="profileEmail">user@example.com</div>
    <div class="user-qq" id="profileQQ">QQ: --</div>
    <div class="user-role" id="profileRole">MARKER</div>
    <div class="btn-group">
      <button class="btn-logout" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> 退出</button>
      <a href="admin.php" class="btn-admin" id="adminBtn" style="display:none;"><i class="fas fa-tools"></i> 进入后台</a>
    </div>
  </div>
  <div class="auth-tabs" id="authTabs">
    <button class="active" data-tab="login">登录</button>
    <button data-tab="register">注册</button>
  </div>
  <!-- 登录 -->
  <form class="auth-form active" id="loginForm">
    <div class="form-error" id="loginError"></div>
    <div class="form-success" id="loginSuccess"></div>
    <label>用户名 / 邮箱</label>
    <div class="input-group"><i class="fas fa-user"></i><input type="text" id="loginUsername" placeholder="请输入用户名或邮箱" required /></div>
    <label>密码</label>
    <div class="input-group"><i class="fas fa-lock"></i><input type="password" id="loginPassword" placeholder="请输入密码" required /></div>
    <button type="submit" class="btn-primary">登录</button>
  </form>
  <!-- 注册 -->
  <form class="auth-form" id="registerForm">
    <div class="form-error" id="registerError"></div>
    <div class="form-success" id="registerSuccess"></div>
    <label>用户名（唯一）</label>
    <div class="input-group"><i class="fas fa-user"></i><input type="text" id="regUsername" placeholder="请设置用户名" required /></div>
    <label>邮箱</label>
    <div class="input-group"><i class="fas fa-envelope"></i><input type="email" id="regEmail" placeholder="请输入邮箱" required /></div>
    <label>密码（至少6位）</label>
    <div class="input-group"><i class="fas fa-lock"></i><input type="password" id="regPassword" placeholder="设置密码" required minlength="6" /></div>
    <label>QQ号（选填）</label>
    <div class="input-group"><i class="fab fa-qq"></i><input type="text" id="regQQ" placeholder="请输入QQ号" /></div>
    <button type="submit" class="btn-primary">注册</button>
  </form>
</div>