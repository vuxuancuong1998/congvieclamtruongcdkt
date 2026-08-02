<?php
require 'header.php';

$jobDetail = isset($job_detail) && is_object($job_detail) ? $job_detail : (object)array();
$relatedJobs = isset($related_jobs) && is_array($related_jobs) ? $related_jobs : array();
$featuredJobs = isset($featured_jobs) && is_array($featured_jobs) ? $featured_jobs : array();
$jobCanApply = !empty($job_can_apply);
$jobIsApplied = !empty($job_is_applied);
$jobApplyMessage = isset($job_apply_message) ? trim((string)$job_apply_message) : '';
$jobApplyMessageType = isset($job_apply_message_type) ? trim((string)$job_apply_message_type) : '';
$jobDeadlineExpired = !empty($job_deadline_expired);
$jobCandidateProfile = isset($job_candidate_profile) && is_object($job_candidate_profile) ? $job_candidate_profile : null;
$showJobSupportModal = !empty($show_job_support_modal);
$jobSupportCsrfToken = isset($job_support_csrf_token) ? (string)$job_support_csrf_token : '';
$jobUrl = general::getInstance()->permalink((int)($jobDetail->id ?? 0), 'job_post');
$siteScriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']) : '';
$siteBasePath = rtrim(dirname($siteScriptName), '/');
if($siteBasePath === '/' || $siteBasePath === '.'){
  $siteBasePath = '';
}
$jobApiBaseUrl = $siteBasePath.'/api';

function jobDetailH($value){
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function jobDetailAsset($value){
  $value = trim((string)$value);
  if($value === ''){
    return '';
  }
  if(preg_match('#^(https?:)?//#i', $value)){
    return $value;
  }
  return XC_URL.'/'.ltrim($value, '/');
}

function jobDetailTextLines($value, $fallback = ''){
  $value = trim((string)$value);
  if($value === ''){
    $value = $fallback;
  }
  if($value === ''){
    return array();
  }
  $value = str_replace(array("\r\n", "\r"), "\n", strip_tags($value));
  $parts = preg_split('/\n+/', $value);
  $items = array();
  foreach($parts as $part){
    $part = trim($part, " \t\n\r\0\x0B-•");
    if($part !== ''){
      $items[] = $part;
    }
  }
  return $items;
}

function jobDetailDate($value, $fallback = 'Đang cập nhật'){
  $time = $value ? strtotime((string)$value) : false;
  return $time ? date('d/m/Y', $time) : $fallback;
}

function jobDetailDeadlineLabel($value){
  $time = $value ? strtotime((string)$value.' 23:59:59') : false;
  if(!$time){
    return 'Hạn cuối nộp hồ sơ: Đang cập nhật';
  }
  if($time < time()){
    return 'Hết hạn nộp hồ sơ';
  }
  return 'Hạn cuối nộp hồ sơ: '.date('d/m/Y', $time);
}

function jobDetailInitials($text){
  $text = trim((string)$text);
  if($text === ''){
    return 'VL';
  }
  $words = preg_split('/\s+/u', $text);
  $initials = '';
  foreach($words as $word){
    if($word === ''){
      continue;
    }
    $initials .= mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8');
    if(mb_strlen($initials, 'UTF-8') >= 2){
      break;
    }
  }
  return $initials !== '' ? $initials : 'VL';
}
$jobTitle = trim((string)($jobDetail->title ?? 'Chi tiết tuyển dụng'));
$companyName = trim((string)($jobDetail->company_name ?? 'Nhà tuyển dụng'));
$companyLogo = jobDetailAsset($jobDetail->logo_url ?? '');
$salaryName = trim((string)($jobDetail->salary_name ?? 'Thỏa thuận'));
$vacancies = trim((string)($jobDetail->vacancies ?? $jobDetail->quantity ?? 'Đang cập nhật'));
$provinceName = trim((string)($jobDetail->province_name ?? 'Đang cập nhật'));
$workType = trim((string)($jobDetail->work_type ?? ''));
$workTypeLabelMap = array(
  'full_time' => 'Full-time',
  'part_time' => 'Part-time',
  'remote' => 'Remote',
  'hybrid' => 'Hybrid',
  'internship' => 'Thực tập',
  'contract' => 'Hợp đồng'
);
$workTypeLabel = isset($workTypeLabelMap[$workType]) ? $workTypeLabelMap[$workType] : ($workType !== '' ? $workType : 'Đang cập nhật');
$companyAddress = trim((string)($jobDetail->company_address ?? 'Đang cập nhật'));
$companySize = trim((string)($jobDetail->company_size ?? 'Đang cập nhật'));
$websiteUrl = trim((string)($jobDetail->website_url ?? ''));
$jobCategoryName = trim((string)($jobDetail->job_category_name ?? 'Đang cập nhật'));
$jobDescriptionItems = jobDetailTextLines($jobDetail->job_description ?? $jobDetail->responsibilities ?? '', 'Nội dung công việc đang được cập nhật.');
$jobRequirementItems = jobDetailTextLines($jobDetail->other_requirements ?? '', 'Yêu cầu ứng viên đang được cập nhật.');
$jobBenefitItems = jobDetailTextLines($jobDetail->benefits_description ?? '', 'Quyền lợi đang được cập nhật.');
$jobShareUrl = $jobUrl;
?>

<?php if($showJobSupportModal){ ?>
<style>
body.jd-support-modal-open{overflow:hidden}
.jd-support-modal{position:fixed;inset:0;z-index:10050;display:flex;align-items:center;justify-content:center;padding:20px;opacity:0;visibility:hidden;transition:opacity .2s ease,visibility .2s ease;font-family:'Inter',system-ui,sans-serif}
.jd-support-modal.open{opacity:1;visibility:visible}
.jd-support-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.62);backdrop-filter:blur(3px)}
.jd-support-dialog{position:relative;width:min(100%,510px);max-height:calc(100vh - 40px);overflow:auto;background:#fff;border-radius:20px;box-shadow:0 24px 70px rgba(15,23,42,.3);transform:translateY(14px) scale(.98);transition:transform .2s ease}
.jd-support-modal.open .jd-support-dialog{transform:translateY(0) scale(1)}
.jd-support-head{padding:28px 60px 12px 28px}
.jd-support-head h2{margin:0;color:#172033;font-size:23px;line-height:1.35;font-weight:800}
.jd-support-head p{margin:8px 0 0;color:#64748b;font-size:14px;line-height:1.6}
.jd-support-close{position:absolute;right:18px;top:18px;width:38px;height:38px;border:0;border-radius:50%;background:#f1f5f9;color:#475569;font-size:22px;line-height:1;cursor:pointer;display:grid;place-items:center}
.jd-support-close:hover{background:#e2e8f0;color:#0f172a}
.jd-support-form{padding:10px 28px 24px}
.jd-support-field{margin-bottom:16px}
.jd-support-field label{display:block;margin-bottom:7px;color:#26344d;font-size:14px;font-weight:700}
.jd-support-required{color:#dc2626}
.jd-support-field input{width:100%;height:48px;box-sizing:border-box;border:1px solid #d7dfeb;border-radius:11px;padding:0 14px;color:#172033;background:#fff;font:inherit;outline:none;transition:border-color .15s ease,box-shadow .15s ease}
.jd-support-field input:focus{border-color:#1769aa;box-shadow:0 0 0 3px rgba(23,105,170,.12)}
.jd-support-field input.invalid{border-color:#dc2626;box-shadow:0 0 0 3px rgba(220,38,38,.08)}
.jd-support-error{display:block;min-height:18px;margin-top:5px;color:#dc2626;font-size:12px}
.jd-support-message{display:none;margin:0 0 14px;padding:10px 12px;border-radius:9px;background:#fff1f2;color:#b42318;font-size:13px;line-height:1.45}
.jd-support-message.show{display:block}
.jd-support-submit{width:100%;min-height:49px;border:0;border-radius:11px;background:linear-gradient(135deg,#0d4e96,#1672b8);color:#fff;font-size:15px;font-weight:800;cursor:pointer;box-shadow:0 8px 22px rgba(13,78,150,.2)}
.jd-support-submit:hover{filter:brightness(1.04)}
.jd-support-submit:disabled{opacity:.68;cursor:wait}
.jd-support-footer{display:flex;justify-content:center;align-items:center;gap:9px;padding:17px 24px 21px;border-top:1px solid #e8edf4;background:#f8fafc;border-radius:0 0 20px 20px;color:#64748b;font-size:14px}
.jd-support-footer a,.jd-support-login{border:0;background:none;padding:0;color:#0d5ea6;font:inherit;font-weight:800;text-decoration:none;cursor:pointer}
.jd-support-footer a:hover,.jd-support-login:hover{text-decoration:underline}
.jd-support-separator{color:#cbd5e1}
@media(max-width:560px){.jd-support-modal{padding:12px;align-items:flex-end}.jd-support-dialog{width:100%;max-height:calc(100vh - 24px);border-radius:18px 18px 12px 12px}.jd-support-head{padding:24px 54px 8px 20px}.jd-support-head h2{font-size:20px}.jd-support-form{padding:10px 20px 20px}.jd-support-footer{flex-wrap:wrap;padding:15px 18px 18px}.jd-support-close{right:14px;top:14px}}
</style>
<?php } ?>

<main class="job-detail-page">
<div class="jd-container">

<div class="jd-breadcrumb">
<a href="<?php echo XC_URL; ?>">Trang chủ</a>
<span>/</span>
<a href="<?php echo XC_URL; ?>/quan-ly-viec-lam.html">Việc làm</a>
<span>/</span>
<span>Chi tiết tuyển dụng</span>
</div>

<section class="jd-hero">
<div class="jd-hero-main">
<div class="jd-logo">
  <?php if($companyLogo !== ''){ ?>
  <img src="<?php echo jobDetailH($companyLogo); ?>" alt="<?php echo jobDetailH($companyName); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:16px">
  <?php }else{ ?>
  <?php echo jobDetailH(jobDetailInitials($companyName)); ?>
  <?php } ?>
</div>

<div class="jd-title-wrap">
<h1><?php echo jobDetailH($jobTitle); ?></h1>
<div class="jd-company-name">
<i class="ti ti-building"></i> <?php echo jobDetailH($companyName); ?>
</div>

<div class="jd-tags">
<span class="jd-tag"><?php echo jobDetailH($salaryName); ?></span>
<span class="jd-tag">Tuyển <?php echo jobDetailH($vacancies); ?></span>
<span class="jd-tag"><?php echo jobDetailH($provinceName); ?></span>
<span class="jd-tag"><?php echo jobDetailH($workTypeLabel); ?></span>
</div>
</div>
</div>

<div class="jd-actions">
  <?php if($jobCanApply){ ?>
  <button class="jd-btn jd-btn-primary" type="button" id="jobApplyBtn" data-job-id="<?php echo intval($jobDetail->id ?? 0); ?>">
  <i class="ti ti-send"></i> Ứng tuyển ngay
  </button>
  <?php }elseif($jobIsApplied){ ?>
  <button class="jd-btn jd-btn-primary" type="button" disabled>
  <i class="ti ti-circle-check"></i> Đã ứng tuyển
  </button>
  <?php } ?>

  <!-- <button class="jd-btn jd-btn-outline" type="button" onclick="window.location.href='<?php echo jobDetailH(XC_URL.'/quan-ly-viec-lam.html'); ?>'">
  <i class="ti ti-arrow-left"></i> 
  </button> -->
</div>
<?php if($jobApplyMessage !== ''){ ?>
<div class="jd-highlight" style="margin-top:16px;color:<?php echo $jobApplyMessageType === 'success' ? '#15803d' : '#b42318'; ?>;background:<?php echo $jobApplyMessageType === 'success' ? '#eefbf3' : '#fff1f2'; ?>;border-color:<?php echo $jobApplyMessageType === 'success' ? '#b7ebc6' : '#fecdd3'; ?>;">
  <?php echo jobDetailH($jobApplyMessage); ?>
</div>
<?php } ?>
</section>

<div class="jd-layout">

<div class="jd-main">

<section class="jd-card">
<h2 class="jd-section-title">
<i class="ti ti-building"></i> Thông tin doanh nghiệp
</h2>

<div class="jd-company-head">
<div class="jd-company-logo-small">
  <?php if($companyLogo !== ''){ ?>
  <img src="<?php echo jobDetailH($companyLogo); ?>" alt="<?php echo jobDetailH($companyName); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%">
  <?php }else{ ?>
  <?php echo jobDetailH(jobDetailInitials($companyName)); ?>
  <?php } ?>
</div>

<div>
<h3><?php echo jobDetailH($companyName); ?></h3>
<p>Lĩnh vực: <?php echo jobDetailH($jobCategoryName); ?></p>
<div class="jd-verified">
<i class="ti ti-circle-check"></i>
<?php echo intval($jobDetail->verified_status ?? 0) === 1 ? 'Đã liên kết với Trường Cao đẳng Kon Tum' : 'Thông tin doanh nghiệp đã được xác minh'; ?>
</div>
</div>
</div>

<div class="jd-info-grid">
<div class="jd-info-item">
<div class="jd-info-label">Địa chỉ</div>
<div class="jd-info-value"><?php echo jobDetailH($companyAddress); ?></div>
</div>

<div class="jd-info-item">
<div class="jd-info-label">Quy mô nhân sự</div>
<div class="jd-info-value"><?php echo jobDetailH($companySize); ?></div>
</div>

<div class="jd-info-item">
<div class="jd-info-label">Website</div>
<div class="jd-info-value"><?php echo $websiteUrl !== '' ? '<a href="'.jobDetailH($websiteUrl).'" target="_blank" rel="noopener">'.jobDetailH($websiteUrl).'</a>' : 'Đang cập nhật'; ?></div>
</div>

<div class="jd-info-item">
<div class="jd-info-label">Ngành nghề</div>
<div class="jd-info-value"><?php echo jobDetailH($jobCategoryName); ?></div>
</div>
</div>

<div class="jd-highlight" style="margin-top:16px">
<?php echo jobDetailH(trim((string)($jobDetail->company_description ?? 'Doanh nghiệp đang mở rộng cơ hội tuyển dụng và sẵn sàng kết nối cùng ứng viên phù hợp.'))); ?>
</div>
</section>

<section class="jd-card">
<h2 class="jd-section-title">
<i class="ti ti-briefcase"></i> Thông tin tuyển dụng
</h2>

<div class="jd-info-grid">
<div class="jd-info-item">
<div class="jd-info-label">Vị trí tuyển dụng</div>
<div class="jd-info-value"><?php echo jobDetailH($jobTitle); ?></div>
</div>

<div class="jd-info-item">
<div class="jd-info-label">Số lượng tuyển</div>
<div class="jd-info-value"><?php echo jobDetailH($vacancies); ?></div>
</div>

<div class="jd-info-item">
<div class="jd-info-label">Hình thức làm việc</div>
<div class="jd-info-value"><?php echo jobDetailH($workTypeLabel); ?></div>
</div>

<div class="jd-info-item">
<div class="jd-info-label">Hạn nộp hồ sơ</div>
<div class="jd-info-value"><?php echo jobDetailH($jobDeadlineExpired ? 'Hết hạn nộp hồ sơ' : jobDetailDate($jobDetail->deadline ?? '', 'Đang cập nhật')); ?></div>
</div>
</div>
</section>

<section class="jd-card">
<h2 class="jd-section-title">
<i class="ti ti-list-check"></i> Mô tả công việc
</h2>

<div class="jd-content">
<ul>
<?php foreach($jobDescriptionItems as $item){ ?>
<li><?php echo jobDetailH($item); ?></li>
<?php } ?>
</ul>
</div>
</section>

<section class="jd-card">
<h2 class="jd-section-title">
<i class="ti ti-user-check"></i> Yêu cầu ứng viên
</h2>

<div class="jd-content">
<ul>
<?php foreach($jobRequirementItems as $item){ ?>
<li><?php echo jobDetailH($item); ?></li>
<?php } ?>
</ul>
</div>
</section>

<section class="jd-card">
<h2 class="jd-section-title">
<i class="ti ti-gift"></i> Quyền lợi
</h2>

<div class="jd-content">
<ul>
<?php foreach($jobBenefitItems as $item){ ?>
<li><?php echo jobDetailH($item); ?></li>
<?php } ?>
</ul>
</div>
</section>

<section class="jd-card">
<h2 class="jd-section-title">
<i class="ti ti-share-3"></i> Chia sẻ bài đăng
</h2>

<div class="jd-share">
<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($jobShareUrl); ?>" target="_blank" rel="noopener"><i class="ti ti-brand-facebook"></i></a>
</div>
</section>

</div>

<div class="jd-sidebar">

<section class="jd-card">
<div class="jd-deadline">
<i class="ti ti-calendar-due"></i>
<?php echo jobDetailH(jobDetailDeadlineLabel($jobDetail->deadline ?? '')); ?>
</div>
</section>

<section class="jd-card">
<h2 class="jd-section-title">
<i class="ti ti-map-pin"></i> Địa điểm làm việc
</h2>

<div class="jd-side-list">
<div class="jd-side-item">
<i class="ti ti-building"></i>
<span><?php echo jobDetailH($companyName); ?></span>
</div>

<div class="jd-side-item">
<i class="ti ti-map-pin"></i>
<span><?php echo jobDetailH($companyAddress); ?></span>
</div>

<div class="jd-side-item">
<i class="ti ti-clock"></i>
<span><?php echo jobDetailH($workTypeLabel); ?></span>
</div>
</div>
</section>

<section class="jd-card">
<h2 class="jd-section-title">
<i class="ti ti-news"></i> Bài viết liên quan
</h2>

<div class="jd-related-list">
<?php if(count($relatedJobs) > 0){ foreach($relatedJobs as $relatedJob){
  $relatedUrl = general::getInstance()->permalink((int)$relatedJob->id, 'job_post');
?>
<a href="<?php echo jobDetailH($relatedUrl); ?>" class="jd-related-item">
<div class="jd-related-icon"><i class="ti ti-briefcase"></i></div>
<div class="jd-related-content">
<h4><?php echo jobDetailH($relatedJob->title ?? 'Việc làm liên quan'); ?></h4>
<p><?php echo jobDetailH(($relatedJob->company_name ?? 'Nhà tuyển dụng').' · '.jobDetailDeadlineLabel($relatedJob->deadline ?? '')); ?></p>
</div>
</a>
<?php }}else{ ?>
<div class="jd-highlight">Chưa có việc làm liên quan cùng ngành nghề.</div>
<?php } ?>
</div>
</section>

</div>

</div>


<section class="jd-featured">
<div class="jd-slider-top">
<h2 class="jd-section-title" style="margin-bottom:0">
<i class="ti ti-star"></i> Bài đăng nổi bật
</h2>

<div class="jd-slider-controls">
<button class="jd-slider-btn" type="button" onclick="jdFeaturedSlide(-1)">
<i class="ti ti-chevron-left"></i>
</button>
<button class="jd-slider-btn" type="button" onclick="jdFeaturedSlide(1)">
<i class="ti ti-chevron-right"></i>
</button>
</div>
</div>

<div class="jd-slider-wrap jd-featured-slider">
<div class="jd-slider-track" id="jdFeaturedTrack">
<?php if(count($featuredJobs) > 0){ foreach($featuredJobs as $featuredJob){
  $featuredUrl = general::getInstance()->permalink((int)$featuredJob->id, 'job_post');
  $featuredLogo = jobDetailAsset($featuredJob->logo_url ?? '');
  $featuredCompany = trim((string)($featuredJob->company_name ?? 'Nhà tuyển dụng'));
?>
<a href="<?php echo jobDetailH($featuredUrl); ?>" class="jd-feature-card">
<div class="jd-feature-head">
<div class="jd-feature-logo">
  <?php if($featuredLogo !== ''){ ?>
  <img src="<?php echo jobDetailH($featuredLogo); ?>" alt="<?php echo jobDetailH($featuredCompany); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:14px">
  <?php }else{ ?>
  <?php echo jobDetailH(jobDetailInitials($featuredCompany)); ?>
  <?php } ?>
</div>
<div class="jd-feature-info">
<h3><?php echo jobDetailH($featuredJob->title ?? 'Việc làm mới'); ?></h3>
<p><?php echo jobDetailH($featuredCompany); ?></p>
</div>
</div>
<div class="jd-feature-tags">
<span><?php echo jobDetailH($featuredJob->salary_name ?? 'Thỏa thuận'); ?></span>
<span><?php echo jobDetailH($featuredJob->province_name ?? 'Đang cập nhật'); ?></span>
<span><?php echo jobDetailH(isset($workTypeLabelMap[$featuredJob->work_type ?? '']) ? $workTypeLabelMap[$featuredJob->work_type] : ($featuredJob->work_type ?? 'Đang tuyển')); ?></span>
</div>
<div class="jd-feature-footer">
<span class="jd-feature-deadline"><?php echo jobDetailH(jobDetailDeadlineLabel($featuredJob->deadline ?? '')); ?></span>
<span class="jd-feature-view">Xem chi tiết</span>
</div>
</a>
<?php }} ?>
</div>
</div>

<?php if(count($featuredJobs) === 0){ ?>
<div class="jd-highlight">Chưa có bài đăng nổi bật để hiển thị.</div>
<?php } ?>
<div class="jd-slider-dots" id="jdFeaturedDots"></div>
</section>

</div>
</main>

<?php if($showJobSupportModal){ ?>
<div class="jd-support-modal" id="jobSupportModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="jobSupportModalTitle">
  <div class="jd-support-backdrop" data-support-close></div>
  <div class="jd-support-dialog" role="document">
    <button type="button" class="jd-support-close" data-support-close aria-label="Đóng cửa sổ hỗ trợ"><i class="ti ti-x"></i></button>
    <div class="jd-support-head">
      <h2 id="jobSupportModalTitle">Hỗ trợ tìm việc nhanh nhất</h2>
      <p>Bạn vui lòng điền thông tin để được hỗ trợ tìm việc nhanh nhất.</p>
    </div>
    <form class="jd-support-form" id="jobSupportForm" novalidate>
      <input type="hidden" name="job_id" value="<?php echo intval($jobDetail->id ?? 0); ?>">
      <input type="hidden" name="csrf_token" value="<?php echo jobDetailH($jobSupportCsrfToken); ?>">
      <div class="jd-support-message" id="jobSupportMessage" role="alert"></div>
      <div class="jd-support-field">
        <label for="jobSupportFullName">Họ và tên <span class="jd-support-required">*</span></label>
        <input type="text" id="jobSupportFullName" name="full_name" maxlength="150" autocomplete="name" placeholder="Nhập họ và tên">
        <span class="jd-support-error" data-error-for="full_name"></span>
      </div>
      <div class="jd-support-field">
        <label for="jobSupportPhone">SĐT <span class="jd-support-required">*</span></label>
        <input type="tel" id="jobSupportPhone" name="phone" maxlength="20" autocomplete="tel" inputmode="tel" placeholder="Nhập số điện thoại">
        <span class="jd-support-error" data-error-for="phone"></span>
      </div>
      <div class="jd-support-field">
        <label for="jobSupportEmail">Email <span class="jd-support-required">*</span></label>
        <input type="email" id="jobSupportEmail" name="email" maxlength="191" autocomplete="email" inputmode="email" placeholder="Nhập địa chỉ email">
        <span class="jd-support-error" data-error-for="email"></span>
      </div>
      <button type="submit" class="jd-support-submit" id="jobSupportSubmit">Lưu thông tin</button>
    </form>
    <div class="jd-support-footer">
      <span>Bạn đã có tài khoản?</span>
      <button type="button" class="jd-support-login" id="jobSupportLogin">Đăng nhập</button>
      <span class="jd-support-separator">hoặc</span>
      <a href="<?php echo jobDetailH(XC_URL.'/dang-ky-tai-khoan.html'); ?>">Đăng ký</a>
    </div>
  </div>
</div>
<?php } ?>

<script>
<?php if($showJobSupportModal){ ?>
(function(){
  var modal = document.getElementById('jobSupportModal');
  var form = document.getElementById('jobSupportForm');
  var submitButton = document.getElementById('jobSupportSubmit');
  var message = document.getElementById('jobSupportMessage');
  var loginButton = document.getElementById('jobSupportLogin');
  var lastActiveElement = null;
  if(!modal || !form) return;

  function openModal(){
    lastActiveElement = document.activeElement;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('jd-support-modal-open');
    setTimeout(function(){
      var firstInput = document.getElementById('jobSupportFullName');
      if(firstInput) firstInput.focus();
    }, 100);
  }

  function closeModal(){
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('jd-support-modal-open');
    if(lastActiveElement && typeof lastActiveElement.focus === 'function'){
      setTimeout(function(){ lastActiveElement.focus(); }, 80);
    }
  }

  function clearErrors(){
    message.classList.remove('show');
    message.textContent = '';
    form.querySelectorAll('.jd-support-error').forEach(function(item){ item.textContent = ''; });
    form.querySelectorAll('input.invalid').forEach(function(input){ input.classList.remove('invalid'); });
  }

  function showErrors(errors){
    Object.keys(errors || {}).forEach(function(field){
      var errorNode = form.querySelector('[data-error-for="' + field + '"]');
      var input = form.querySelector('[name="' + field + '"]');
      if(errorNode) errorNode.textContent = errors[field];
      if(input) input.classList.add('invalid');
    });
  }

  modal.querySelectorAll('[data-support-close]').forEach(function(item){
    item.addEventListener('click', closeModal);
  });
  document.addEventListener('keydown', function(event){
    if(event.key === 'Escape' && modal.classList.contains('open')) closeModal();
  });

  if(loginButton){
    loginButton.addEventListener('click', function(){
      closeModal();
      var headerLoginButton = document.querySelector('header .js-login-open, .top-header .js-login-open, .js-login-open');
      if(headerLoginButton){
        setTimeout(function(){ headerLoginButton.click(); }, 100);
      }
    });
  }

  form.addEventListener('submit', function(event){
    event.preventDefault();
    clearErrors();
    submitButton.disabled = true;
    submitButton.textContent = 'Đang lưu...';

    fetch('<?php echo $jobApiBaseUrl; ?>/saveJobSupportRequest', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: new FormData(form)
    })
      .then(function(response){
        return response.text().then(function(text){
          try { return text ? JSON.parse(text) : null; }
          catch(error){ throw new Error('Hệ thống đang trả về dữ liệu không hợp lệ.'); }
        });
      })
      .then(function(result){
        if(!result || Number(result.status) !== 200){
          if(result && result.errors) showErrors(result.errors);
          throw new Error((result && result.message) || 'Không thể lưu thông tin lúc này.');
        }
        closeModal();
        if(window.Swal){
          Swal.fire({ icon:'success', title:'Đã lưu thông tin', text:result.message });
        }else{
          window.alert(result.message);
        }
      })
      .catch(function(error){
        message.textContent = error.message;
        message.classList.add('show');
      })
      .finally(function(){
        submitButton.disabled = false;
        submitButton.textContent = 'Lưu thông tin';
      });
  });

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', openModal);
  }else{
    openModal();
  }
})();
<?php } ?>

(function(){
  var current = 0;
  var timer = null;
  var delay = 5000;

  function getVisibleCount(){
    if(window.innerWidth <= 560) return 1;
    if(window.innerWidth <= 1024) return 2;
    return 3;
  }

  function getMaxSlide(){
    var track = document.getElementById('jdFeaturedTrack');
    if(!track) return 0;
    var cards = track.querySelectorAll('.jd-feature-card');
    return Math.max(cards.length - getVisibleCount(), 0);
  }

  function renderDots(){
    var dotsWrap = document.getElementById('jdFeaturedDots');
    if(!dotsWrap) return;

    var max = getMaxSlide();
    if(max <= 0){
      dotsWrap.innerHTML = '';
      return;
    }
    var html = '';
    for(var i = 0; i <= max; i++){
      html += '<button type="button" class="jd-slider-dot ' + (i === current ? 'active' : '') + '" onclick="jdFeaturedGo(' + i + ')"></button>';
    }
    dotsWrap.innerHTML = html;
  }

  function updateSlider(){
    var track = document.getElementById('jdFeaturedTrack');
    if(!track) return;
    var cards = track.querySelectorAll('.jd-feature-card');
    if(!cards.length) return;

    var max = getMaxSlide();
    if(current > max) current = 0;
    if(current < 0) current = max;

    var gap = 14;
    var cardWidth = cards[0].offsetWidth + gap;
    track.style.transition = 'transform .45s ease';
    track.style.transform = 'translateX(-' + (current * cardWidth) + 'px)';
    renderDots();
  }

  function resetAuto(){
    clearInterval(timer);
    if(getMaxSlide() <= 0) return;
    timer = setInterval(function(){
      current += 1;
      updateSlider();
    }, delay);
  }

  window.jdFeaturedSlide = function(step){
    current += step;
    updateSlider();
    resetAuto();
  };

  window.jdFeaturedGo = function(index){
    current = index;
    updateSlider();
    resetAuto();
  };

  window.addEventListener('resize', function(){
    updateSlider();
    resetAuto();
  });

  updateSlider();
  resetAuto();
})();

(function(){
  var applyButton = document.getElementById('jobApplyBtn');
  if(!applyButton) return;

  applyButton.addEventListener('click', function(){
    var jobId = applyButton.getAttribute('data-job-id') || '';
    if(!jobId) return;

    applyButton.disabled = true;
    fetch('<?php echo $jobApiBaseUrl; ?>/applyJob', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: new URLSearchParams({ job_id: jobId }).toString()
    })
      .then(function(response){
        return response.text().then(function(text){
          var result = null;
          try {
            result = text ? JSON.parse(text) : null;
          } catch (parseError) {
            throw new Error(text ? text.replace(/<[^>]*>/g, ' ').trim() : 'Hệ thống đang trả về dữ liệu không hợp lệ.');
          }
          return result;
        });
      })
      .then(function(result){
        if(result && result.requires_verification && result.return_url){
          window.location.href = result.return_url;
          return;
        }
        if(!result || Number(result.status) !== 200){
          throw new Error((result && result.message) || 'Không thể ứng tuyển công việc này.');
        }
        if(window.Swal){
          Swal.fire({
            icon: 'success',
            title: 'Ứng tuyển thành công',
            text: result.message
          }).then(function(){
            window.location.reload();
          });
        }else{
          window.alert(result.message);
          window.location.reload();
        }
      })
      .catch(function(error){
        if(window.Swal){
          Swal.fire({ icon: 'error', title: 'Không thể ứng tuyển', text: error.message });
        }else{
          window.alert(error.message);
        }
      })
      .finally(function(){
        applyButton.disabled = false;
      });
  });
})();
</script>
<?php require_once 'footer.php'; ?>