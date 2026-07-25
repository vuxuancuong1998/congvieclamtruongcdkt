<?php require "config.php";?>
<?php
$siteUserLoggedIn = isset($_SESSION['user']['id']) && (int)$_SESSION['user']['id'] > 0;
$siteUserId = $siteUserLoggedIn ? (int)($_SESSION['user']['id'] ?? 0) : 0;
$siteCandidateId = 0;
$siteUserName = $siteUserLoggedIn ? trim((string)($_SESSION['user']['full_name'] ?? '')) : '';
if($siteUserLoggedIn && $siteUserName === ''){
  $siteUserName = strstr((string)($_SESSION['user']['email'] ?? ''), '@', true) ?: 'Tài khoản';
}
$siteUserInitial = $siteUserName !== '' ? mb_strtoupper(mb_substr($siteUserName, 0, 1, 'UTF-8'), 'UTF-8') : 'U';
$siteUserGroup = (string)($_SESSION['user']['group'] ?? '');
if($siteUserLoggedIn && $siteUserGroup === '4'){
  global $db;
  if(isset($db) && is_object($db)){
    $db->query("SELECT id FROM hicrm_candidates WHERE user_id = '".intval($siteUserId)."' LIMIT 1");
    if($db->num_row() > 0){
      $siteCandidateRow = $db->fetch_object(true);
      $siteCandidateId = (int)($siteCandidateRow->id ?? 0);
    }
  }
}
$siteUserProfileUrl = $siteUserGroup == '2'
  ? XC_URL.'/quan-ly-nha-tuyen-dung.html'
  : XC_URL.'/quan-ly-ho-so-ung-vien.html'.($siteCandidateId > 0 ? '/'.$siteCandidateId : '');
$siteScriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']) : '';
$siteBasePath = rtrim(dirname($siteScriptName), '/');
if($siteBasePath === '/' || $siteBasePath === '.'){
  $siteBasePath = '';
}
$siteApiBaseUrl = $siteBasePath.'/api';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Việc Làm  - Trường Cao đẳng Kon Tum</title>
<meta name="description" content="Cổng thông tin việc làm Trường Cao đẳng Kon Tum - Kết nối sinh viên, người tìm việc và doanh nghiệp tuyển dụng uy tín"/>
<link rel="icon" href="<?php echo $template_path; ?>/assets/images/logo.png" type="image/x-icon">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css"/>
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800&family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?php echo $template_path;?>/assets/css/style.css?version<?php echo time();?>">
<link rel="stylesheet" href="<?php echo $template_path;?>/assets/css/job.css?version<?php echo time();?>">
<link rel="stylesheet" href="<?php echo $template_path;?>/assets/css/cv.css?version<?php echo time();?>">
<link rel="stylesheet" href="<?php echo $template_path;?>/assets/css/styledetailcv.css?version<?php echo time();?>">
<link rel="stylesheet" href="<?php echo $template_path;?>/assets/css/events.css?version<?php echo time();?>">
<link rel="stylesheet" href="<?php echo $template_path;?>/assets/css/introduce.css?version<?php echo time();?>">
<link rel="stylesheet" href="<?php echo $template_path;?>/assets/css/dbhemployee.css?version<?php echo time();?>">
<link rel="stylesheet" href="<?php echo $template_path;?>/assets/css/register.css?version<?php echo time();?>">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/jquery-migrate-3.5.2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-ui-dist@1.13.3/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.20.0/dist/jquery.validate.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.20.0/dist/additional-methods.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-form@4.3.0/dist/jquery.form.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-mask-plugin@1.14.16/dist/jquery.mask.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/owl.carousel@2.3.4/dist/owl.carousel.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
 <script>
			$(document).ready(function(){
				$("#btlogin").click(function(){
					console.log('a');
					var email = $('#email').val();
					var password = $('#password').val();
					$.ajax({
						"type": "POST",
						"url": "<?php echo $siteApiBaseUrl; ?>/login",
						"data": {
							'email': email,
							'password': password,
              'login_context': 'frontend'
						},
						"dataType":'json',
						success:function(data){
							if(data.status == 200){
                         Swal.fire({
                          toast: true,
                          // position: 'bottom-end',
                          icon: 'success',
                          title: data.message,
                          showConfirmButton: false,
                          timer: 1200,
                          timerProgressBar: true,
                          didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer);
                            toast.addEventListener('mouseleave', Swal.resumeTimer);
                          }
                      }).then((result) => {
                          window.location.href = data.return_url; });
							}else{
								Swal.fire({
								  icon: 'error',
								  title: "Đăng nhập thất bại!",
								  text: data.message,
								  footer: '<a href=""></a>'
								})
							}
						}
					
					});
					return false;
				});
			});
	  </script>
</head>
<body>

<!-- APP BAR / BANNER -->
<div class="app-bar">
  <div class="app-bar-inner">
    <div class="app-banner-left">
      <div class="app-banner-icon"><i class="ti ti-building-community"></i></div>
      <div>
        <div class="app-banner-title">Cổng thông tin việc làm Trường Cao đẳng Kon Tum</div>
        <div class="app-banner-sub">Kết nối sinh viên, người tìm việc và doanh nghiệp tuyển dụng uy tín</div>
      </div>
    </div>
    <div class="app-banner-right">
      <div class="app-contact-list">
        <a href="tel:02603860000" class="app-contact-item"><i class="ti ti-phone-call"></i> <?php echo $this->helper->get_config('site_phone'); ?></a>
        <a href="mailto:vieclam@cdkontum.edu.vn" class="app-contact-item"><i class="ti ti-mail"></i><?php echo $this->helper->get_config('site_email'); ?></a>
        <a href="#" class="app-contact-item"><i class="ti ti-map-pin"></i> Quảng Ngãi</a>
      </div>
    </div>
  </div>
</div>

<!-- HEADER -->
<header class="header">
  <div class="header-top">
    
<div class="header-top-inner">

  <a href="<?php echo XC_URL; ?>" class="logo" aria-label="Trang chủ">
    <div class="logo-text"><img src="<?php echo $template_path; ?>/assets/images/logo.png" alt="Logo"></div>
  </a>
  <div class="mobile-site-title">Cổng thông tin việc làm Trường Cao đẳng Kon Tum</div>

  <nav class="header-nav desktop-nav">
    <a href="<?php echo XC_URL; ?>">Trang chủ</a>
    <a href="<?php echo XC_URL; ?>/gioi-thieu.html">Giới thiệu</a>
    <a href="<?php echo XC_URL; ?>/tin-tuc-su-kien.html">Tin tức</a>

    <div class="nav-item">
      <a href="#">Việc làm <i class="ti ti-chevron-down"></i></a>
      <div class="dropdown-menu">
          <a href="<?php echo XC_URL; ?>/quan-ly-viec-lam.html" class="dropdown-item"><i class="ti ti-bolt"></i> Việc tìm người</a>
          <a href="<?php echo XC_URL; ?>/quan-ly-ung-vien.html" class="dropdown-item"><i class="ti ti-star"></i> Người tìm việc</a>
      </div>
    </div>

    <div class="nav-item">
      <a href="#">Sàn việc làm <i class="ti ti-chevron-down"></i></a>
      <div class="dropdown-menu">
          <a href="<?php echo XC_URL; ?>/gioi-thieu-san-viec-lam.html" class="dropdown-item"><i class="ti ti-building"></i> Giới thiệu sàn</a>
          <a href="<?php echo XC_URL; ?>/quy-trinh-san-viec-lam.html" class="dropdown-item"><i class="ti ti-list-details"></i> Quy trình sàn</a>
          <a href="<?php echo XC_URL; ?>/ket-qua-san-viec-lam.html" class="dropdown-item"><i class="ti ti-chart-bar"></i> Kết quả sàn</a>
          <a href="<?php echo XC_URL; ?>/san-viec-lam-online.html" class="dropdown-item"><i class="ti ti-broadcast"></i>Sàn việc làm Online</a>
      </div>
    </div>

    <a href="<?php echo XC_URL; ?>/huong-dan.html">Hướng dẫn</a>
    <a href="<?php echo XC_URL; ?>/lien-he.html">Liên hệ</a>
  </nav>

  <div class="header-actions">
    <?php if($siteUserLoggedIn): ?>
      <div class="header-account" id="headerAccount">
        <button type="button" class="header-account-toggle" id="headerAccountToggle" aria-expanded="false" aria-haspopup="menu">
          <span class="header-account-avatar"><?php echo htmlspecialchars($siteUserInitial, ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="header-account-name"><?php echo htmlspecialchars($siteUserName, ENT_QUOTES, 'UTF-8'); ?></span>
          <i class="ti ti-chevron-down"></i>
        </button>
        <div class="header-account-menu" id="headerAccountMenu" role="menu">
          <div class="header-account-summary">
            <strong><?php echo htmlspecialchars($siteUserName, ENT_QUOTES, 'UTF-8'); ?></strong>
            <span><?php echo htmlspecialchars((string)($_SESSION['user']['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <a href="<?php echo $siteUserProfileUrl; ?>" role="menuitem"><i class="ti ti-user-circle"></i> Quản lý hồ sơ</a>
          <button type="button" class="js-change-password-open" role="menuitem"><i class="ti ti-key"></i> Đổi mật khẩu</button>
          <a href="<?php echo XC_URL; ?>/home/logout" class="header-account-logout" role="menuitem"><i class="ti ti-logout-2"></i> Đăng xuất</a>
        </div>
      </div>
    <?php else: ?>
      <button type="button" class="btn-login js-login-open"><i class="ti ti-login-2" style="margin-right:4px"></i> Đăng nhập</button>
      <button class="btn-post js-employer-login-open"><i class="ti ti-building"></i>Nhà tuyển dụng</button>
    <?php endif; ?>
  </div>

      <button class="hamburger" id="hamburgerBtn" aria-label="Mở menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>

  </header>


<!-- LOGIN MODAL -->
<div class="login-modal" id="loginModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="loginModalTitle">
  <div class="login-modal-backdrop" id="loginModalBackdrop"></div>
  <div class="login-modal-card" role="document">
    <div class="login-modal-head">
      <button type="button" class="login-modal-close" id="loginModalClose" aria-label="Đóng đăng nhập"><i class="ti ti-x"></i></button>
      <div class="login-modal-title-wrap">
        <div class="login-modal-icon"><i class="ti ti-user-shield"></i></div>
        <div>
          <h3 class="login-modal-title" id="loginModalTitle">Đăng nhập tài khoản</h3>
          <div class="login-modal-sub">Truy cập nhanh để ứng tuyển, quản lý CV và theo dõi việc làm phù hợp.</div>
        </div>
      </div>
    </div>
    <div class="login-modal-body">
      <form class="login-form" action="#" method="post">
        <div class="login-field">
          <label for="loginEmail">Email</label>
          <div class="login-input-wrap">
            <i class="ti ti-mail"></i>
            <input type="email" id="email" name="email" placeholder="Nhập email của bạn" autocomplete="email" required>
          </div>
        </div>

        <div class="login-field">
          <label for="loginPassword">Mật khẩu</label>
          <div class="login-input-wrap">
            <i class="ti ti-lock"></i>
            <input type="password" id="password" name="password" placeholder="Nhập mật khẩu" autocomplete="current-password" required>
          </div>
        </div>

        <div class="login-options">
          <!-- <label class="login-remember"><input type="checkbox" name="remember"> Ghi nhớ đăng nhập</label> -->
          <a href="<?php echo XC_URL; ?>/quen-mat-khau.php" class="login-forgot">Quên mật khẩu?</a>
        </div>

        <button type="submit" class="login-submit" id='loginSubmitBtn'><i class="ti ti-login-2"></i> Đăng nhập</button>

        

        <div class="login-register-note">Bạn chưa có tài khoản? <a href="<?php echo XC_URL; ?>/dang-ky-tai-khoan.html">Đăng ký miễn phí</a></div>
      </form>
    </div>
  </div>
</div>

<?php if($siteUserLoggedIn): ?>
<div class="account-password-modal" id="accountPasswordModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="accountPasswordTitle">
  <div class="account-password-backdrop" data-password-close></div>
  <div class="account-password-card" role="document">
    <button type="button" class="account-password-close" data-password-close aria-label="Đóng"><i class="ti ti-x"></i></button>
    <div class="account-password-icon"><i class="ti ti-lock-password"></i></div>
    <h3 id="accountPasswordTitle">Đổi mật khẩu</h3>
    <p>Cập nhật mật khẩu mới để bảo vệ tài khoản của bạn.</p>
    <form id="accountPasswordForm">
      <label>Mật khẩu hiện tại
        <input type="password" name="oldpass" autocomplete="current-password" required>
      </label>
      <label>Mật khẩu mới
        <input type="password" name="newpass" minlength="6" autocomplete="new-password" required>
      </label>
      <label>Xác nhận mật khẩu mới
        <input type="password" name="confirm_password" minlength="6" autocomplete="new-password" required>
      </label>
      <div class="account-password-message" id="accountPasswordMessage" role="alert"></div>
      <button type="submit" class="account-password-submit"><i class="ti ti-device-floppy"></i> Cập nhật mật khẩu</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- MOBILE DRAWER MENU -->
<div class="mobile-menu" id="mobileMenu" aria-hidden="true">
  <div class="mobile-menu-backdrop" id="menuBackdrop"></div>
  <div class="mobile-menu-drawer">
    <div class="mobile-menu-header">
      <div class="mm-logo"><span class="red">Việc</span><span class="dark">Làm</span><span class="red"></span></div>
      <button class="mobile-menu-close" id="menuCloseBtn"><i class="ti ti-x"></i></button>
    </div>

    <div class="mm-user-section">
      <?php if($siteUserLoggedIn): ?>
        <div class="mm-account-head" role="button" tabindex="0" aria-expanded="false" onclick="this.parentElement.classList.toggle('open');this.setAttribute('aria-expanded',this.parentElement.classList.contains('open')?'true':'false')" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click()}"><span><?php echo htmlspecialchars($siteUserInitial, ENT_QUOTES, 'UTF-8'); ?></span><div><strong><?php echo htmlspecialchars($siteUserName, ENT_QUOTES, 'UTF-8'); ?></strong><small><?php echo htmlspecialchars((string)($_SESSION['user']['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small></div><i class="ti ti-chevron-down"></i></div>
        <div class="mm-account-links">
          <a href="<?php echo $siteUserProfileUrl; ?>"><i class="ti ti-user-circle"></i> Quản lý hồ sơ</a>
          <button type="button" class="js-change-password-open"><i class="ti ti-key"></i> Đổi mật khẩu</button>
          <a href="<?php echo XC_URL; ?>/home/logout" class="mm-account-logout"><i class="ti ti-logout-2"></i> Đăng xuất</a>
        </div>
      <?php else: ?>
        <div class="mm-user-label">Sẵn sàng để bắt đầu công việc mơ ước?</div>
        <div class="mm-btn-group">
          <button type="button" class="mm-btn-login js-login-open">Đăng nhập</button>
          <button class="mm-btn-ntd" style="background:#333"><a href="<?php echo XC_URL; ?>/dang-ky-tai-khoan.html" style="color: #fff; text-decoration: none;">Đăng ký</a></button>
        </div>
        
      <?php endif; ?>
    </div>

    <nav class="mm-nav mm-nav-desktop-match">
      <a href="<?php echo XC_URL; ?>"><i class="ti ti-home"></i>Trang chủ</a>
      <a href="<?php echo XC_URL; ?>/gioi-thieu.html"><i class="ti ti-info-circle"></i>Giới thiệu</a>
      <a href="<?php echo XC_URL; ?>/tin-tuc-su-kien.html"><i class="ti ti-news"></i>Tin tức</a>

      <div class="mm-nav-item">
        <button type="button" class="mm-menu-link" onclick="toggleMobileSubmenu(this)"><span><i class="ti ti-briefcase"></i>Việc làm</span>&nbsp;<i class="ti ti-chevron-down mm-arrow"></i></button>
        <div class="mm-submenu">
          <div class="mm-submenu-inner">
            <a href="<?php echo XC_URL; ?>/quan-ly-viec-lam.html"><i class="ti ti-building"></i>&nbsp;Việc tìm người</a>
            <a href="<?php echo XC_URL; ?>/quan-ly-ung-vien.html"><i class="ti ti-user-search"></i>&nbsp;Người tìm việc</a>
          </div>
        </div>
      </div>

      <div class="mm-nav-item">
        <button type="button" class="mm-menu-link" onclick="toggleMobileSubmenu(this)"><span><i class="ti ti-building-community"></i>Sàn việc làm</span>&nbsp;<i class="ti ti-chevron-down mm-arrow"></i></button>
        <div class="mm-submenu">
          <div class="mm-submenu-inner">
            <a href="<?php echo XC_URL; ?>/gioi-thieu-san-viec-lam.html"><i class="ti ti-info-square-rounded"></i>&nbsp;Giới thiệu sàn</a>
            <a href="<?php echo XC_URL; ?>/quy-trinh-san-viec-lam.html"><i class="ti ti-list-check"></i>&nbsp;Quy trình sàn</a>
            <a href="<?php echo XC_URL; ?>/ket-qua-san-viec-lam.html"><i class="ti ti-chart-bar"></i>&nbsp;Kết quả sàn</a>
            <a href="<?php echo XC_URL; ?>/san-viec-lam-online.html"><i class="ti ti-broadcast"></i>&nbsp;Sàn việc làm Online</a>
          </div>
        </div>
      </div>

      <a href="<?php echo XC_URL; ?>/huong-dan.html"><i class="ti ti-help-circle"></i>Hướng dẫn</a>
      <a href="<?php echo XC_URL; ?>/lien-he.html"><i class="ti ti-mail"></i>Liên hệ</a>
    </nav>

    <div class="mm-bottom">
      <?php if(!$siteUserLoggedIn): ?>
        <button class="mm-btn-ntd js-employer-login-open" style="width:100%; margin-bottom: 15px;">
          <i class="ti ti-speakerphone"></i> Cho Nhà Tuyển Dụng
        </button>
      <?php endif; ?>
      <div class="mm-bottom-label">Kết nối xã hội</div>
      <div class="mm-socials">
        <a href="#"><i class="ti ti-brand-facebook"></i></a>
      </div>
    </div>
  </div>
</div>
<script>
function toggleMobileSubmenu(el){
  var item = el.closest('.mm-nav-item');
  if(item){ item.classList.toggle('active'); }
}
(function(){
  var btn = document.getElementById('hamburgerBtn');
  var menu = document.getElementById('mobileMenu');
  var closeBtn = document.getElementById('menuCloseBtn');
  var backdrop = document.getElementById('menuBackdrop');
  if (!btn || !menu || !closeBtn || !backdrop) return;

  function openMenu(){
    document.body.classList.add('menu-open');
    btn.classList.add('open');
    btn.setAttribute('aria-expanded', 'true');
    menu.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeMenu(){
    document.body.classList.remove('menu-open');
    btn.classList.remove('open');
    btn.setAttribute('aria-expanded', 'false');
    menu.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  btn.addEventListener('click', function(e){
    e.preventDefault();
    if (document.body.classList.contains('menu-open')) closeMenu();
    else openMenu();
  });
  closeBtn.addEventListener('click', closeMenu);
  backdrop.addEventListener('click', closeMenu);
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && document.body.classList.contains('menu-open')) closeMenu();
  });
})();


(function(){
  var modal = document.getElementById('loginModal');
  var closeBtn = document.getElementById('loginModalClose');
  var backdrop = document.getElementById('loginModalBackdrop');
  var loginButtons = document.querySelectorAll('.js-login-open');
  var firstInput = document.getElementById('loginEmail');
  var lastActiveElement = null;
  if (!modal || !closeBtn || !backdrop || !loginButtons.length) return;

  function closeMobileMenuIfOpen(){
    var hamburgerBtn = document.getElementById('hamburgerBtn');
    var mobileMenu = document.getElementById('mobileMenu');
    if(document.body.classList.contains('menu-open')){
      document.body.classList.remove('menu-open');
      document.body.style.overflow = '';
      if(hamburgerBtn){
        hamburgerBtn.classList.remove('open');
        hamburgerBtn.setAttribute('aria-expanded','false');
      }
      if(mobileMenu){ mobileMenu.setAttribute('aria-hidden','true'); }
    }
  }

  function openLoginModal(){
    lastActiveElement = document.activeElement;
    closeMobileMenuIfOpen();
    modal.classList.add('open');
    modal.setAttribute('aria-hidden','false');
    document.body.classList.add('login-modal-open');
    setTimeout(function(){ if(firstInput) firstInput.focus(); }, 80);
  }

  function closeLoginModal(){
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden','true');
    document.body.classList.remove('login-modal-open');
    if(lastActiveElement && typeof lastActiveElement.focus === 'function'){
      setTimeout(function(){ lastActiveElement.focus(); }, 80);
    }
  }

  loginButtons.forEach(function(button){
    button.addEventListener('click', function(e){
      e.preventDefault();
      openLoginModal();
    });
  });

  closeBtn.addEventListener('click', closeLoginModal);
  backdrop.addEventListener('click', closeLoginModal);
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape' && modal.classList.contains('open')) closeLoginModal();
  });
})();
</script>

<script>
(function(){
  window.hideFrontendLoginModal = function(){
    var modal = document.getElementById('loginModal');
    if(!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('login-modal-open');
  };

  function resendVerificationEmail(payload){
    return fetch('<?php echo $siteApiBaseUrl; ?>/resendVerificationEmail', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
      body: new URLSearchParams({
        user_id: payload.user_id || '',
        email: payload.email || ''
      }).toString()
    }).then(function(response){
      return response.text().then(function(text){
        try {
          return JSON.parse(text);
        } catch (error) {
          throw new Error(text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() || 'Phan hoi may chu khong hop le.');
        }
      });
    });
  }

  window.handleFrontendVerificationRequired = function(result){
    var fullName = result && result.full_name ? result.full_name : 'Chưa cập nhật';
    var email = result && result.email ? result.email : 'Chưa cập nhật';
    var userId = result && result.user_id ? result.user_id : '';

    if(!window.Swal){
      window.hideFrontendLoginModal();
      window.alert('Tài khoản chưa được xác thực. Vui lòng kiểm tra email: ' + email);
      return Promise.resolve();
    }

    return Swal.fire({
      icon: 'warning',
      title: 'Xác thực tài khoản',
      html: '<div style="text-align:left;line-height:1.7;">'
        + '<div><strong>Họ và tên tài khoản:</strong> ' + fullName + '</div>'
        + '<div><strong>Email:</strong> ' + email + '</div>'
        + '<div style="margin-top:10px;color:#7a5c00;">Tài khoản của bạn chưa được xác thực email.</div>'
        + '</div>',
      showCancelButton: true,
      confirmButtonText: 'Gửi Email xác thực ngay',
      cancelButtonText: 'Đóng',
      showLoaderOnConfirm: true,
      allowOutsideClick: function(){ return !Swal.isLoading(); },
      preConfirm: function(){
        window.hideFrontendLoginModal();
        return resendVerificationEmail({ user_id: userId, email: email }).then(function(sendResult){
          if(!sendResult || Number(sendResult.status) !== 200){
            throw new Error((sendResult && sendResult.message) || 'Không thể gửi email xác thực.');
          }
          return sendResult;
        }).catch(function(error){
          Swal.showValidationMessage(error.message);
          return false;
        });
      }
    }).then(function(dialogResult){
      if(!dialogResult.isConfirmed || !dialogResult.value){ return; }
      return Swal.fire({
        icon: 'success',
        title: 'Đã gửi email xác thực',
        text: dialogResult.value.message
      });
    });
  };
})();
</script>

<script>
(function(){
  var loginForm = document.querySelector('.login-form');
  if(!loginForm) return;

  loginForm.addEventListener('submit', function(event){
    event.preventDefault();
    var submitButton = document.getElementById('loginSubmitBtn');
    var email = (loginForm.elements.email || {}).value || '';
    var password = (loginForm.elements.password || {}).value || '';
    if(!email || !password) return;
    window.hideFrontendLoginModal();
    if(submitButton) submitButton.disabled = true;

    fetch('<?php echo $siteApiBaseUrl; ?>/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
      body: new URLSearchParams({ email: email, password: password, login_context: 'frontend' }).toString()
    })
      .then(function(response){ return response.json(); })
      .then(function(result){
        if(result && result.requires_verification){
          if(result.return_url){
            window.location.href = result.return_url;
            return;
          }
          return window.handleFrontendVerificationRequired(result).then(function(){
            var handledError = new Error('Tài khoản chưa được xác thực email.');
            handledError.verificationHandled = true;
            throw handledError;
          });
        }
        if(!result || Number(result.status) !== 200){ throw new Error((result && result.message) || 'Đăng nhập không thành công.'); }
        window.hideFrontendLoginModal();
        window.location.href = result.return_url || '<?php echo XC_URL; ?>';
      })
      .catch(function(error){
        if(error && error.verificationHandled){ return; }
        if(window.Swal){ Swal.fire({ icon: 'error', title: 'Không thể đăng nhập', text: error.message }); }
        else { window.alert(error.message); }
      })
      .finally(function(){ if(submitButton) submitButton.disabled = false; });
  });
})();

(function(){
  var account = document.getElementById('headerAccount');
  var toggle = document.getElementById('headerAccountToggle');
  var menu = document.getElementById('headerAccountMenu');
  var passwordModal = document.getElementById('accountPasswordModal');
  var passwordForm = document.getElementById('accountPasswordForm');
  var passwordMessage = document.getElementById('accountPasswordMessage');
  var passwordOpeners = document.querySelectorAll('.js-change-password-open');
  var passwordClosers = document.querySelectorAll('[data-password-close]');

  function closeAccountMenu(){
    if(account) account.classList.remove('open');
    if(toggle) toggle.setAttribute('aria-expanded', 'false');
  }
  function openPasswordModal(){
    if(!passwordModal) return;
    closeAccountMenu();
    document.body.classList.remove('menu-open');
    document.body.style.overflow = '';
    var mobileMenu = document.getElementById('mobileMenu');
    var hamburger = document.getElementById('hamburgerBtn');
    if(mobileMenu) mobileMenu.setAttribute('aria-hidden', 'true');
    if(hamburger){ hamburger.classList.remove('open'); hamburger.setAttribute('aria-expanded', 'false'); }
    passwordModal.classList.add('open');
    passwordModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('account-password-open');
    if(passwordForm) passwordForm.reset();
    if(passwordMessage){ passwordMessage.textContent = ''; passwordMessage.className = 'account-password-message'; }
  }
  function closePasswordModal(){
    if(!passwordModal) return;
    passwordModal.classList.remove('open');
    passwordModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('account-password-open');
  }

  if(toggle && account){
    toggle.addEventListener('click', function(){
      var isOpen = account.classList.toggle('open');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }
  passwordOpeners.forEach(function(opener){ opener.addEventListener('click', openPasswordModal); });
  passwordClosers.forEach(function(closer){ closer.addEventListener('click', closePasswordModal); });
  document.addEventListener('click', function(event){ if(account && !account.contains(event.target)) closeAccountMenu(); });
  document.addEventListener('keydown', function(event){ if(event.key === 'Escape'){ closeAccountMenu(); closePasswordModal(); } });

  if(passwordForm){
    passwordForm.addEventListener('submit', function(event){
      event.preventDefault();
      var oldPassword = passwordForm.elements.oldpass.value;
      var newPassword = passwordForm.elements.newpass.value;
      var confirmation = passwordForm.elements.confirm_password.value;
      if(newPassword !== confirmation){
        passwordMessage.textContent = 'Xác nhận mật khẩu mới chưa khớp.';
        passwordMessage.className = 'account-password-message error';
        return;
      }
      fetch('<?php echo XC_URL; ?>/api/changepass', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ oldpass: oldPassword, newpass: newPassword }).toString()
      })
        .then(function(response){ return response.json(); })
        .then(function(result){
          if(!result || Number(result.status) !== 200){ throw new Error((result && result.message) || 'Không thể đổi mật khẩu.'); }
          passwordMessage.textContent = 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.';
          passwordMessage.className = 'account-password-message success';
          window.setTimeout(function(){ window.location.href = '<?php echo XC_URL; ?>'; }, 900);
        })
        .catch(function(error){
          passwordMessage.textContent = error.message;
          passwordMessage.className = 'account-password-message error';
        });
    });
  }
})();
</script>


<!-- EMPLOYER LOGIN MODAL INTEGRATED -->
<div class="employer-login-modal" id="employerLoginModal" aria-hidden="true">
  <div class="employer-login-backdrop" data-employer-close></div>

  <div class="employer-login-card" role="dialog" aria-modal="true" aria-labelledby="employerLoginTitle">
    <div class="employer-login-left">
      <div class="employer-login-badge">
        <i class="ti ti-building-skyscraper"></i> Không gian tuyển dụng chuyên nghiệp
      </div>

      <h2>Kết nối doanh nghiệp với ứng viên phù hợp nhanh hơn.</h2>
      <p>Đăng nhập để đăng tin tuyển dụng, quản lý ứng viên, theo dõi lịch phỏng vấn và xây dựng thương hiệu tuyển dụng chuyên nghiệp.</p>

      <!-- <div class="employer-login-stats">
        <div class="employer-login-stat">
          <strong>2.000+</strong>
          <span>Hồ sơ ứng viên</span>
        </div>
        <div class="employer-login-stat">
          <strong>500+</strong>
          <span>Doanh nghiệp</span>
        </div>
        <div class="employer-login-stat">
          <strong>24/7</strong>
          <span>Hỗ trợ trực tuyến</span>
        </div>
      </div> -->
    </div>

    <div class="employer-login-right">
      <div class="employer-login-head">
        <button type="button" class="employer-login-close" data-employer-close aria-label="Đóng">
          <i class="ti ti-x"></i>
        </button>
        <div class="employer-login-title" id="employerLoginTitle">Đăng nhập Nhà tuyển dụng</div>
        <div class="employer-login-sub">Quản lý tin tuyển dụng và hồ sơ ứng viên của doanh nghiệp.</div>
      </div>

      <form action="#" method="post" class="employer-login-form" novalidate>
        <div class="employer-field">
          <label>Email hoặc số điện thoại</label>
          <div class="employer-input-wrap">
            <i class="ti ti-mail"></i>
            <input type="text" name="employer_account" placeholder="Nhập email hoặc số điện thoại" autocomplete="username" required>
          </div>
        </div>

        <div class="employer-field">
          <label>Mật khẩu</label>
          <div class="employer-input-wrap">
            <i class="ti ti-lock"></i>
            <input type="password" name="employer_password" placeholder="Nhập mật khẩu" autocomplete="current-password" required>
          </div>
        </div>

        <div class="employer-options">
         
          <a href="<?php echo XC_URL; ?>/quen-mat-khau" class="employer-forgot">Quên mật khẩu?</a>
        </div>

        <button type="submit" class="employer-submit">
          <i class="ti ti-login-2"></i> Đăng nhập Nhà tuyển dụng
        </button>

        <div class="employer-login-message" role="alert" aria-live="polite"></div>

        

        <div class="employer-note">
          Chưa có tài khoản doanh nghiệp? <a href="<?php echo XC_URL; ?>/dang-ky-tai-khoan.html">Đăng ký ngay</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  function onReady(fn){
    if(document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  onReady(function(){
    var modal = document.getElementById('employerLoginModal');
    if(!modal) return;

    var openButtons = document.querySelectorAll('.js-employer-login-open, .btn-post');
    var closeButtons = modal.querySelectorAll('[data-employer-close]');
    var loginForm = modal.querySelector('.employer-login-form');
    var loginMessage = modal.querySelector('.employer-login-message');
    var loginSubmit = modal.querySelector('.employer-submit');

    function closeOtherMenus(){
      document.body.classList.remove('menu-open');
      var mobileMenu = document.getElementById('mobileMenu');
      var hamburger = document.getElementById('hamburgerBtn');
      if(mobileMenu) mobileMenu.setAttribute('aria-hidden','true');
      if(hamburger){
        hamburger.classList.remove('open');
        hamburger.setAttribute('aria-expanded','false');
      }
    }

    function openEmployerModal(e){
      if(e) e.preventDefault();
      closeOtherMenus();
      modal.classList.add('open');
      modal.setAttribute('aria-hidden','false');
      document.body.classList.add('employer-modal-open');
    }

    function closeEmployerModal(){
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden','true');
      document.body.classList.remove('employer-modal-open');
    }

    openButtons.forEach(function(btn){
      btn.addEventListener('click', openEmployerModal);
    });

    closeButtons.forEach(function(btn){
      btn.addEventListener('click', closeEmployerModal);
    });

    document.addEventListener('keydown', function(e){
      if(e.key === 'Escape' && modal.classList.contains('open')){
        closeEmployerModal();
      }
    });

    if(loginForm){
      loginForm.addEventListener('submit', function(event){
        event.preventDefault();
        var account = (loginForm.elements.employer_account || {}).value || '';
        var password = (loginForm.elements.employer_password || {}).value || '';
        if(!account.trim() || !password){
          if(loginMessage){ loginMessage.textContent = 'Vui lòng nhập email/số điện thoại và mật khẩu.'; loginMessage.className = 'employer-login-message error'; }
          return;
        }
        if(loginMessage){ loginMessage.textContent = ''; loginMessage.className = 'employer-login-message'; }
        if(loginSubmit){ loginSubmit.disabled = true; loginSubmit.classList.add('is-loading'); }

        fetch('<?php echo $siteApiBaseUrl; ?>/login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
          body: new URLSearchParams({ email: account.trim(), password: password, login_type: 'employer', login_context: 'frontend' }).toString()
        })
          .then(function(response){ return response.json(); })
          .then(function(result){
            if(result && result.requires_verification){
              if(result.return_url){
                window.location.href = result.return_url;
                return;
              }
              return window.handleFrontendVerificationRequired(result).then(function(){
                var handledError = new Error('Tài khoản chưa được xác thực email.');
                handledError.verificationHandled = true;
                throw handledError;
              });
            }
            if(!result || Number(result.status) !== 200){ throw new Error((result && result.message) || 'Không thể đăng nhập.'); }
            window.location.href = result.return_url || '<?php echo XC_URL; ?>/quan-ly-nha-tuyen-dung.html';
          })
          .catch(function(error){
            if(error && error.verificationHandled){ return; }
            if(loginMessage){ loginMessage.textContent = error.message; loginMessage.className = 'employer-login-message error'; }
          })
          .finally(function(){ if(loginSubmit){ loginSubmit.disabled = false; loginSubmit.classList.remove('is-loading'); } });
      });
    }
  });
})();
</script>
