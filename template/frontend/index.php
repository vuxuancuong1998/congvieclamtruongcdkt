
<?php require "header.php"; ?>
<?php
$featured_jobs = isset($featured_jobs) && is_array($featured_jobs) ? $featured_jobs : array();
$province_jobs = isset($province_jobs) && is_array($province_jobs) ? $province_jobs : array();
$linked_employers = isset($linked_employers) && is_array($linked_employers) ? $linked_employers : array();
$recent_employers = isset($recent_employers) && is_array($recent_employers) ? $recent_employers : array();
$urgent_jobs = isset($urgent_jobs) && is_array($urgent_jobs) ? $urgent_jobs : array();
$job_provinces = isset($job_provinces) && is_array($job_provinces) ? $job_provinces : array();
$job_categories_with_counts = isset($job_categories_with_counts) && is_array($job_categories_with_counts) ? $job_categories_with_counts : array();
$featured_candidates = isset($featured_candidates) && is_array($featured_candidates) ? $featured_candidates : array();
$home_featured_news = isset($home_featured_news) && is_array($home_featured_news) ? $home_featured_news : array();
$featured_job_filters = isset($featured_job_filters) && is_array($featured_job_filters) ? $featured_job_filters : array();
$urgent_job_filters = isset($urgent_job_filters) && is_array($urgent_job_filters) ? $urgent_job_filters : array();
$featured_jobs_total_pages = isset($featured_jobs_total_pages) ? (int)$featured_jobs_total_pages : 1;
$province_jobs_total_pages = isset($province_jobs_total_pages) ? (int)$province_jobs_total_pages : 1;
$urgent_jobs_total_pages = isset($urgent_jobs_total_pages) ? (int)$urgent_jobs_total_pages : 1;
$featured_job_filters_json = json_encode($featured_job_filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$urgent_job_filters_json = json_encode($urgent_job_filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

function homeJobH($value){
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function homeJobInitials($name){
  $name = trim((string)$name);
  if($name === ''){ return 'JOB'; }
  $parts = preg_split('/\s+/', $name);
  $letters = '';
  foreach($parts as $part){
    if($part !== ''){ $letters .= mb_substr($part, 0, 1, 'UTF-8'); }
    if(mb_strlen($letters, 'UTF-8') >= 3){ break; }
  }
  return mb_strtoupper($letters ?: mb_substr($name, 0, 3, 'UTF-8'), 'UTF-8');
}

function homeEmployerLogoUrl($value){
  $value = trim((string)$value);
  if($value === ''){ return ''; }
  if(preg_match('#^(https?:)?//#i', $value) || strpos($value, 'data:') === 0){ return $value; }
  return XC_URL.'/'.ltrim($value, '/');
}

function homeEmployerCard($employer, $compact = false, $isClone = false){
  $id = isset($employer->id) ? (int)$employer->id : 0;
  $name = isset($employer->company_name) && $employer->company_name ? $employer->company_name : 'Nhà tuyển dụng';
  $logo = homeEmployerLogoUrl($employer->logo_url ?? '');
  $jobs = isset($employer->published_jobs) ? (int)$employer->published_jobs : 0;
  $href = XC_URL.'/quan-ly-viec-lam.html?employer_id='.$id;
  $initials = homeJobInitials($name);
  $cloneAttribute = $isClone ? ' tabindex="-1"' : '';

  if($compact){
    echo '<a class="employer-logo-slide" href="'.homeJobH($href).'" title="'.homeJobH($name).'" aria-label="Việc làm tại '.homeJobH($name).'"'.$cloneAttribute.'>';
    echo '<span class="employer-slide-logo">';
    if($logo !== ''){ echo '<img src="'.homeJobH($logo).'" alt="'.homeJobH($name).'" loading="lazy" onerror="this.style.display=\'none\'">'; }
    echo '</span></a>';
    return;
  }

  echo '<a class="employer-featured-card" href="'.homeJobH($href).'" aria-label="Xem việc làm tại '.homeJobH($name).'"'.$cloneAttribute.'>';
  echo '<span class="employer-featured-logo">';
  if($logo !== ''){ echo '<img src="'.homeJobH($logo).'" alt="'.homeJobH($name).'" loading="lazy" onerror="this.style.display=\'none\'">'; }
  echo '</span>';
  echo '<span class="employer-featured-name" title="'.homeJobH($name).'">'.homeJobH($name).'</span>';
  echo '<span class="employer-featured-count">'.number_format($jobs, 0, ',', '.').' việc làm đang tuyển</span>';
  echo '</a>';
}

function homeEmployerSliderItems($employers, $minimumItems){
  if(empty($employers)){ return array(); }
  $items = array_values($employers);
  while(count($items) < $minimumItems){
    foreach($employers as $employer){
      $items[] = $employer;
      if(count($items) >= $minimumItems){ break; }
    }
  }
  return $items;
}

function homeJobDateText($value){
  if(!$value){ return 'Mới đăng'; }
  $time = strtotime($value);
  if(!$time){ return 'Mới đăng'; }
  $seconds = max(0, time() - $time);
  $minutes = floor($seconds / 60);
  if($minutes < 1){ return 'Vừa xong'; }
  if($minutes < 60){ return $minutes.' phút trước'; }
  $hours = floor($minutes / 60);
  if($hours < 24){ return $hours.' giờ trước'; }
  $days = floor($hours / 24);
  return $days.' ngày trước';
}

function homeJobDeadlineText($value){
  if(!$value){ return 'Đang cập nhật'; }
  $time = strtotime($value);
  return $time ? date('d/m/Y', $time) : 'Đang cập nhật';
}

function homeJobIsUrgent($job){
  $type = isset($job->job_post_type) ? $job->job_post_type : 'normal';
  return in_array($type, array('urgent', 'hot'), true);
}

function homeJobTypeLabel($job){
  $type = isset($job->job_post_type) ? $job->job_post_type : 'normal';
  // if($type === 'hot'){ return 'HOT'; }
  // if($type === 'urgent'){ return 'Tuyen gap'; }
  return '';
}

function homeJobWorkTypeLabel($value){
  $value = trim((string)$value);
  $labels = array(
    'full_time' => 'Full-time',
    'part_time' => 'Part-time',
    'remote' => 'Remote',
    'hybrid' => 'Hybrid',
    'internship' => 'Thực tập',
    'contract' => 'Hợp đồng'
  );
  return isset($labels[$value]) ? $labels[$value] : ($value !== '' ? $value : 'Đang tuyển');
}

function homeJobExperienceText($value){
  $value = trim((string)$value);
  if($value === '' || $value === '0'){ return 'Chưa yêu cầu KN'; }
  return $value.' năm KN';
}

function homeJobNormalize($value){
  $value = mb_strtolower((string)$value, 'UTF-8');
  $from = array('à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ','è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ','ì','í','ị','ỉ','ĩ','ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ','ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ','ỳ','ý','ỵ','ỷ','ỹ','đ');
  $to = array('a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','e','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y','d');
  return str_replace($from, $to, $value);
}

function homeJobSalaryKey($text){
  $text = homeJobNormalize($text);
  if(strpos($text, '1') !== false && strpos($text, '3') !== false) return '1-3';
  if(strpos($text, '3') !== false && strpos($text, '5') !== false) return '3-5';
  if(strpos($text, '5') !== false && strpos($text, '7') !== false) return '5-7';
  if(strpos($text, '7') !== false && strpos($text, '10') !== false) return '7-10';
  if(strpos($text, '10') !== false && strpos($text, '15') !== false) return '10-15';
  if(strpos($text, '15') !== false && strpos($text, '20') !== false) return '15-20';
  if(strpos($text, '20') !== false) return '20+';
  return '';
}

function homeJobLocationKey($text){
  $text = homeJobNormalize($text);
  if(strpos($text, 'ha noi') !== false) return 'hanoi';
  if(strpos($text, 'ho chi minh') !== false || strpos($text, 'hcm') !== false) return 'tphcm';
  if(strpos($text, 'da nang') !== false) return 'danang';
  if(strpos($text, 'binh duong') !== false) return 'binhduong';
  if(strpos($text, 'can tho') !== false) return 'cantho';
  return '';
}

function homeJobIndustryKey($text){
  $text = homeJobNormalize($text);
  if(strpos($text, 'logistics') !== false || strpos($text, 'kho') !== false) return 'logistics';
  if(strpos($text, 'cong nghe') !== false || strpos($text, 'lap trinh') !== false || strpos($text, 'tester') !== false || strpos($text, 'php') !== false || strpos($text, 'java') !== false) return 'it';
  if(strpos($text, 'nhan su') !== false || strpos($text, 'hr') !== false) return 'hr';
  if(strpos($text, 'cham soc') !== false || strpos($text, 'dich vu') !== false) return 'service';
  if(strpos($text, 'kinh doanh') !== false || strpos($text, 'ban hang') !== false) return 'sales';
  return '';
}

function homeJobCard($job, $extraClass = '', $isLatest = false, $includeDeadline = false){
  $companyName = $job->company_name ?: 'Nhà tuyển dụng';
  $url_logo = $job->logo_url ?? '';
  $urgent = homeJobIsUrgent($job);
  $postType = isset($job->job_post_type) ? $job->job_post_type : 'normal';
  $salary = $job->salary_name ?: '';
  $location = $job->province_name ?: '';
  $industry = $job->job_category_name ?: '';
  $typeLabel = homeJobTypeLabel($job);
  $workType = homeJobWorkTypeLabel($job->work_type ?? '');
  $titleClass = $postType === 'hot' ? ' job-title-hot' : ($postType === 'urgent' ? ' job-title-urgent' : '');
  $classes = trim('job-card job-card-'.$postType.' '.$extraClass.($isLatest ? ' latest-job-card' : ''));
  $href = general::getInstance()->permalink((int)($job->id ?? 0), 'job_post');
  echo '<a href="'.homeJobH($href).'" class="'.$classes.'" data-salary="'.homeJobH(homeJobSalaryKey($salary)).'" data-location="'.homeJobH(homeJobLocationKey($location)).'" data-experience="'.homeJobH($job->experience_years).'" data-industry="'.homeJobH(homeJobIndustryKey($industry.' '.$job->title)).'">';
  // if($isLatest){ echo '<span class="job-new-label">new</span>'; }
  echo '<div class="job-card-header">';
  if($url_logo){ echo '<div class="company-logo"><img src="'.homeJobH($url_logo).'" alt="'.homeJobH($companyName).'" /></div>'; } else { echo '<div class="company-logo" style="background:#eef6ff;color:#0d4e96">'.homeJobH(homeJobInitials($companyName)).'</div>'; }
  echo '<div><div class="job-title'.$titleClass.'">'.homeJobH($job->title).'</div><div class="company-name"><i class="ti ti-building"></i> '.homeJobH($companyName).'</div></div>';
  echo '</div>';
  echo '<div class="job-card-tags">';
  echo '<span class="tag tag-salary">'.homeJobH($salary).'</span>';
  echo '<span class="tag tag-location"><i class="ti ti-map-pin" style="font-size:10px"></i> '.homeJobH($location).'</span>';
  echo '<span class="tag tag-type">'.homeJobH($workType).'</span>';
  echo '<span class="tag tag-experience">'.homeJobH(homeJobExperienceText($job->experience_years ?? '')).'</span>';
  if($isLatest || $includeDeadline){ echo '<span class="tag tag-deadline"><i class="ti ti-calendar-event" style="font-size:10px"></i> Hạn nộp: '.homeJobH(homeJobDeadlineText($job->deadline ?? '')).'</span>'; }
  echo '</div>';
  echo '<div class="job-card-footer">';
  echo '<span class="job-date"><i class="ti ti-clock"></i> '.homeJobH(homeJobDateText($job->published_at ?: $job->created_at)).'</span>';
  // if($urgent){ echo '<span class="urgent-badge">'.homeJobH($typeLabel ?: 'GẤP').'</span>'; }
  echo '</div></a>';
}

function homeCandidateAvatarUrl($value){
  $value = trim((string)$value);
  if($value === ''){ return ''; }
  if(preg_match('#^(https?:)?//#i', $value) || strpos($value, 'data:') === 0){ return $value; }
  return XC_URL.'/'.ltrim($value, '/');
}

function homeCandidateDateText($candidate){
  $raw = $candidate->date_of_birth ?? $candidate->birthday ?? $candidate->dob ?? '';
  $time = $raw ? strtotime((string)$raw) : false;
  return $time ? date('d/m/Y', $time) : 'Đang cập nhật';
}

function homeCandidateMajorText($candidate){
  return trim((string)($candidate->job_category_name ?? $candidate->desired_position ?? 'Ứng viên đang tìm việc')) ?: 'Ứng viên đang tìm việc';
}

function homeCandidateUrl($candidate){
  return general::getInstance()->permalink((int)($candidate->id ?? 0), 'candidate_profile');
}

function homeCandidateName($candidate){
  return trim((string)($candidate->full_name ?? $candidate->candidate_name ?? 'Ứng viên nổi bật')) ?: 'Ứng viên nổi bật';
}

function homeCandidateInitials($candidate){
  $name = homeCandidateName($candidate);
  $parts = preg_split('/\s+/', $name);
  $letters = '';
  foreach((array)$parts as $part){
    if($part !== ''){ $letters .= mb_substr($part, 0, 1, 'UTF-8'); }
    if(mb_strlen($letters, 'UTF-8') >= 2){ break; }
  }
  return mb_strtoupper($letters ?: mb_substr($name, 0, 2, 'UTF-8'), 'UTF-8');
}

function homeCandidateAccentColor($candidate){
  $palette = array('#0d4e96', '#1565c0', '#2e7d32', '#c62828', '#6a1b9a', '#00695c', '#e65100', '#1a237e', '#00838f', '#37474f');
  return $palette[((int)($candidate->id ?? 0)) % count($palette)];
}

function homeNewsImageUrl($value){
  $value = trim((string)$value);
  if($value === ''){ return 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?w=900&h=520&fit=crop'; }
  if(preg_match('#^(https?:)?//#i', $value) || strpos($value, 'data:') === 0){ return $value; }
  return XC_URL.'/uploads/events/'.ltrim($value, '/');
}

function homeNewsExcerpt($news, $length = 120){
  $text = trim(strip_tags((string)($news->event_description ?? '')));
  if($text === ''){ $text = trim(strip_tags((string)($news->event_content ?? ''))); }
  $text = homeNewsFixText($text);
  if($text === ''){ return 'Nội dung đang được cập nhật.'; }
  return mb_strlen($text, 'UTF-8') > $length ? mb_substr($text, 0, $length, 'UTF-8').'...' : $text;
}

function homeNewsDateText($value){
  $time = $value ? strtotime((string)$value) : false;
  return $time ? date('d/m/Y', $time) : 'Đang cập nhật';
}
function homeNewsFixText($value){
  $value = trim((string)$value);
  if($value === ''){ return ''; }
  if(function_exists('mb_convert_encoding') && preg_match('/(?:Ã.|Ä.|áº|á»|â€|Â)/u', $value)){
    $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    if(is_string($converted) && $converted !== ''){ return $converted; }
  }
  return $value;
}

function homeNewsTitle($news){
  $title = homeNewsFixText($news->event_name ?? '');
  return $title !== '' ? $title : 'Bài viết đang cập nhật';
}

function homeNewsUrl($news){
  return general::getInstance()->permalink((int)($news->id ?? 0), 'event');
}
?>

<!-- HERO -->
<style>
  .hero-slider {
    position: relative;
    z-index: 10;
    isolation: isolate;
    overflow: visible;
    min-height: 390px;
    background: #0d4e96;
  }
  .hero-slider.location-dropdown-open {
    z-index: 10000;
  }
  .hero-slider-backgrounds,
  .hero-slider-background,
  .hero-slider-overlay {
    position: absolute;
    inset: 0;
  }
  .hero-slider-backgrounds {
    z-index: -3;
    overflow: hidden;
  }
  .hero-slider-background {
    background-position: center;
    background-size: cover;
    opacity: 0;
    transform: scale(1.04);
    transition: opacity .8s ease, transform 7s ease;
  }
  .hero-slider-background.active {
    opacity: 1;
    transform: scale(1);
  }
  .hero-slider-overlay {
    z-index: -2;
    pointer-events: none;
    background: linear-gradient(90deg, rgba(4, 30, 61, .58) 0%, rgba(13, 78, 150, .28) 55%, rgba(4, 30, 61, .2) 100%);
  }
  .hero-slider .hero-inner {
    position: relative;
    z-index: 2;
  }
  .hero-slider h1,
  .hero-slider .hero-sub {
    color: rgba(255, 255, 255, .94);
    text-shadow: 0 2px 14px rgba(0, 0, 0, .55);
  }
  .hero-slider .hero-badge {
    color: #fff;
    background: rgba(4, 30, 61, .25);
    border-color: rgba(255, 255, 255, .42);
    box-shadow: 0 8px 22px rgba(0, 0, 0, .12);
    backdrop-filter: blur(6px);
  }
  .hero-slider .search-wrap {
    background: rgba(255, 255, 255, .28);
    border-color: rgba(255, 255, 255, .55);
    box-shadow: 0 10px 28px rgba(0, 0, 0, .16);
    backdrop-filter: blur(10px);
  }
  .hero-slider .search-wrap:focus-within {
    background: rgba(255, 255, 255, .4);
    border-color: rgba(255, 255, 255, .9);
  }
  .hero-slider .search-input {
    background: transparent;
    color: #fff;
  }
  .hero-slider .search-input::placeholder {
    color: rgba(255, 255, 255, .78);
  }
  .hero-slider .search-icon,
  .hero-slider .search-location,
  .hero-slider .search-location i.pin,
  .hero-slider .search-location i.chevron {
    color: rgba(255, 255, 255, .92);
  }
  .hero-slider .search-divider {
    background: rgba(255, 255, 255, .42);
  }
  .hero-slider .search-btn {
    background: rgba(13, 78, 150, .68);
    border-left: 1px solid rgba(255, 255, 255, .28);
    backdrop-filter: blur(8px);
  }
  .hero-slider .search-btn:hover {
    background: rgba(13, 78, 150, .9);
  }
  .hero-slider .hero-login-card {
    background: rgba(255, 255, 255, .3);
    border-color: rgba(255, 255, 255, .48);
    box-shadow: 0 12px 30px rgba(0, 0, 0, .18);
    backdrop-filter: blur(10px);
  }
  .hero-slider .hero-login-card p,
  .hero-slider .hero-login-card span {
    color: rgba(255, 255, 255, .94);
    text-shadow: 0 1px 8px rgba(0, 0, 0, .42);
  }
  .hero-slider .btn-google {
    color: #fff;
    background: rgba(255, 255, 255, .18);
    border-color: rgba(255, 255, 255, .5);
    backdrop-filter: blur(7px);
  }
  .hero-slider .btn-google:hover {
    background: rgba(255, 255, 255, .3);
    border-color: rgba(255, 255, 255, .85);
  }
  .hero-slider .btn-login-hero {
    background: rgba(13, 78, 150, .68);
    border: 1px solid rgba(255, 255, 255, .36);
    backdrop-filter: blur(7px);
  }
  .hero-slider .btn-login-hero:hover {
    background: rgba(13, 78, 150, .9);
  }
  .hero-slider .location-dropdown {
    z-index: 500;
    background: rgba(255, 255, 255, .88);
    backdrop-filter: blur(14px);
  }
  .hero-slider-nav {
    position: absolute;
    z-index: 5;
    top: 50%;
    width: 44px;
    height: 44px;
    border: 1px solid rgba(255, 255, 255, .55);
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: rgba(4, 30, 61, .46);
    color: #fff;
    cursor: pointer;
    transform: translateY(-50%);
    transition: background .2s ease, transform .2s ease;
  }
  .hero-slider-nav:hover,
  .hero-slider-nav:focus-visible {
    background: #0d4e96;
    transform: translateY(-50%) scale(1.08);
    outline: 2px solid #fff;
    outline-offset: 2px;
  }
  .hero-slider-prev { left: 16px; }
  .hero-slider-next { right: 16px; }
  .hero-slider-dots {
    position: absolute;
    z-index: 5;
    bottom: 14px;
    left: 50%;
    display: flex;
    gap: 8px;
    transform: translateX(-50%);
  }
  .hero-slider-dot {
    width: 9px;
    height: 9px;
    padding: 0;
    border: 1px solid rgba(255, 255, 255, .8);
    border-radius: 99px;
    background: rgba(255, 255, 255, .42);
    cursor: pointer;
    transition: width .25s ease, background .25s ease;
  }
  .hero-slider-dot.active {
    width: 28px;
    background: #fff;
  }
  @media (max-width: 768px) {
    .hero-slider { min-height: 350px; }
    .hero-slider-nav { width: 36px; height: 36px; }
    .hero-slider-prev { left: 6px; }
    .hero-slider-next { right: 6px; }
    .hero-slider .hero-inner { padding-left: 34px; padding-right: 34px; }
  }
  @media (max-width: 480px) {
    .hero-slider .search-wrap {
      position: relative;
    }
    .hero-slider .search-location-box {
      position: static;
    }
    .hero-slider .location-dropdown.open {
      display: flex;
      flex-direction: column;
      top: calc(100% + 8px);
      right: 0;
      bottom: auto;
      left: 0;
      width: 100%;
      max-height: min(55dvh, 420px);
      padding-bottom: 10px;
      border-radius: 14px;
      background: #fff;
      backdrop-filter: none;
      box-shadow: 0 18px 50px rgba(4, 30, 61, .28);
    }
    .hero-slider .location-dropdown-search {
      flex: 0 0 auto;
    }
    .hero-slider .location-list {
      flex: 1 1 auto;
      min-height: 0;
      max-height: none;
      overscroll-behavior: contain;
    }
  }
  @media (prefers-reduced-motion: reduce) {
    .hero-slider-background { transition: opacity .2s ease; transform: none; }
  }






/* ============================================================
   SECTION
============================================================ */
.sv-section {
  width: 100%;
  box-sizing: border-box;
  padding: 20px 24px;
}

.section-inner {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 18px;
}

.section-title {
  position: relative;
  padding-left: 12px;
  font-size: 20px;
  font-weight: 700;
  color: #1a1a1a;
}

.section-title::before {
  content: "";
  position: absolute;
  left: 0;
  top: 2px;
  bottom: 2px;
  width: 4px;
  background: #2f6fed;
  border-radius: 2px;
}

.see-all {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 14px;
  font-weight: 600;
  color: #2f6fed;
  text-decoration: none;
  white-space: nowrap;
}

.see-all:hover {
  text-decoration: underline;
}

/* ============================================================
   SLIDER WRAP + TRACK
   (giữ đúng behavior JS hiện có: #svTrack dịch chuyển bằng transform)
============================================================ */
#svSliderWrap {
  overflow: hidden;
  position: relative;
  border-radius: 12px;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}

#svTrack {
  display: flex;
  transition: transform 0.55s cubic-bezier(0.4, 0, 0.2, 1);
  will-change: transform;
  width: 100%;
}

/* ============================================================
   GRID (mỗi .sv-grid = 1 trang / page)
============================================================ */
.sv-grid {
  display: grid;
  grid-template-columns: 1fr; /* mobile-first: 1 cột */
  gap: 12px;
  width: 100%;
  min-width: 100%;   /* mỗi trang chiếm trọn 1 "slide" trong track */
  max-width: 100%;
  flex-shrink: 0;
  box-sizing: border-box;
}

/* ============================================================
   CARD
============================================================ */
.sv-card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  width: 100%;
  min-width: 0; /* QUAN TRỌNG: chặn card tự phình rộng hơn cột grid */
  padding: 20px 14px 16px;
  box-sizing: border-box;
  border: 1.5px solid #2f6fed;
  border-radius: 20px;
  background: #fff;
  text-decoration: none;
  color: inherit;
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.sv-card:hover {
  box-shadow: 0 6px 16px rgba(47, 111, 237, 0.15);
  transform: translateY(-2px);
}

/* ============================================================
   AVATAR
============================================================ */
.sv-avatar-wrap {
  width: 70px;
  height: 70px;
  border-radius: 50%;
  overflow: hidden;
  border: 3px solid #2f6fed;
  margin-bottom: 10px;
  flex-shrink: 0;
}

.sv-avatar-photo {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.sv-avatar-fallback {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-weight: 600;
  font-size: 22px;
  text-transform: uppercase;
}

/* ============================================================
   TEXT INFO
============================================================ */
.sv-name {
  width: 100%;
  font-weight: 700;
  font-size: 14px;
  color: #1a1a1a;
  margin-bottom: 4px;
  line-height: 1.3;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sv-dob {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  color: #7a7a7a;
  font-size: 12px;
  margin-bottom: 10px;
  white-space: nowrap;
}

.sv-dob i {
  font-size: 11px;
}

.sv-major {
  max-width: 100%;
  background: #eaf1fd;
  color: #2f6fed;
  font-size: 12px;
  font-weight: 600;
  padding: 5px 12px;
  border-radius: 20px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ============================================================
   BADGES
============================================================ */
.sv-badge,
.sv-badge-xuat-sac {
  position: absolute;
  top: -1px;
  right: 16px;
  font-size: 10px;
  font-weight: 600;
  padding: 4px 9px;
  border-radius: 0 0 8px 8px;
  color: #fff;
}

.sv-badge { background: #2f6fed; }
.sv-badge-xuat-sac { background: #f59e0b; }

/* ============================================================
   PAGINATION (dots + prev/next)
============================================================ */
.sv-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-top: 20px;
}

.jobs-nav {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: 1.5px solid #2f6fed;
  border-radius: 50%;
  background: #fff;
  color: #2f6fed;
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease;
}

.jobs-nav:hover {
  background: #2f6fed;
  color: #fff;
}

.jobs-dots-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.sv-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  border: none;
  background: #cdd8f0;
  cursor: pointer;
  padding: 0;
  transition: background 0.2s ease, width 0.2s ease;
}

.sv-dot.active {
  background: #2f6fed;
  width: 20px;
  border-radius: 4px;
}

/* ============================================================
   RESPONSIVE — mobile-first
============================================================ */
@media (min-width: 400px) {
  .sv-grid { gap: 14px; }
}

@media (min-width: 576px) {
  .sv-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
  }
  .sv-avatar-wrap { width: 80px; height: 80px; }
  .sv-name { font-size: 15px; }
}

@media (min-width: 768px) {
  .sv-grid {
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
  }
  .sv-card { padding: 22px 16px 18px; }
}

@media (min-width: 992px) {
  .sv-grid {
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
  }
}

@media (min-width: 1200px) {
  .sv-grid {
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
  }
}

@media (min-width: 1280px) {
  .sv-grid {
    grid-template-columns: repeat(6, 1fr);
    gap: 20px;
  }
  .sv-avatar-wrap { width: 90px; height: 90px; }
  .sv-name { font-size: 16px; }
  .sv-dob, .sv-major { font-size: 13px; }
  .sv-card { padding: 24px 16px 20px; }
}

@media (min-width: 1536px) {
  .sv-grid { gap: 26px; }
  .sv-card { padding: 26px 18px 22px; }
}
</style>
<section class="hero hero-slider" id="heroSlider" aria-roledescription="carousel" aria-label="Banner việc làm nổi bật">
  <div class="hero-slider-backgrounds" aria-hidden="true">
    <div class="hero-slider-background active" style="background-image:url('https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=2000&q=85')"></div>
    <div class="hero-slider-background" style="background-image:url('https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=2000&q=85')"></div>
    <div class="hero-slider-background" style="background-image:url('https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=2000&q=85')"></div>
  </div>
  <div class="hero-slider-overlay" aria-hidden="true"></div>
  <button type="button" class="hero-slider-nav hero-slider-prev" id="heroSliderPrev" aria-label="Slider trước">
    <i class="ti ti-chevron-left" aria-hidden="true"></i>
  </button>
  <button type="button" class="hero-slider-nav hero-slider-next" id="heroSliderNext" aria-label="Slider tiếp theo">
    <i class="ti ti-chevron-right" aria-hidden="true"></i>
  </button>
  <div class="hero-inner">
    <div class="hero-left">
      <div class="hero-badge"><i class="ti ti-award"></i> Hệ thống cổng thông tin việc làm</div>
      <h1>Trường Cao đẳng Kon Tum<br>Hệ thống kết nối sinh viên - doanh nghiệp</h1>
      <p class="hero-sub">Tìm việc nhanh chóng. Ứng tuyển dễ dàng.</p>

      <div class="search-wrap" id="heroSearchWrap">
        <i class="ti ti-search search-icon"></i>
        <input class="search-input" type="text" placeholder="Vị trí tuyển dụng, tên công ty..."/>
        <div class="search-divider"></div>
        <div class="search-location-box">
          <div class="search-location" id="searchLocationBtn" role="button" tabindex="0" aria-expanded="false" aria-haspopup="listbox">
            <i class="ti ti-map-pin pin"></i>
            <span class="search-location-label" id="searchLocationLabel">Toàn quốc</span>
            <i class="ti ti-chevron-down chevron"></i>
          </div>
          <div class="location-dropdown" id="locationDropdown" role="listbox" aria-hidden="true">
            <div class="location-dropdown-search">
              <i class="ti ti-search"></i>
              <input type="text" id="locationSearchInput" placeholder="Tìm khu vực..." autocomplete="off" aria-label="Tìm khu vực"/>
            </div>
            <ul class="location-list" id="locationList"></ul>
            <p class="location-empty" id="locationEmpty" hidden>Không tìm thấy khu vực phù hợp</p>
          </div>
        </div>
        <button type="button" class="search-btn"><i class="ti ti-search" style="margin-right:6px;vertical-align:-2px"></i> Tìm việc</button>
      </div>

      <!-- <div class="quick-links">
        <a href="https://vieclam.vn/viec-lam-ha-noi-p73.html" class="quick-link">Việc làm Hà Nội</a>
        <a href="https://vieclam.vn/viec-lam-tp-hcm-p122.html" class="quick-link">Việc làm TPHCM</a>
        <a href="https://vieclam.vn/viec-lam-marketing-o12.html" class="quick-link">Việc làm Marketing</a>
        <a href="https://vieclam.vn/viec-lam-ke-toan-o17.html" class="quick-link">Việc làm kế toán</a>
        <a href="https://vieclam.vn/viec-lam-binh-duong-p119.html" class="quick-link">Việc làm Bình Dương</a>
        <a href="https://vieclam.vn/viec-lam-nhan-su-o22.html" class="quick-link">Tuyển dụng nhân sự</a>
        <a href="https://vieclam.vn/viec-lam-tuyen-nhanh.html" class="quick-link special">⚡ Việc đi làm ngay</a>
        <a href="https://vieclam.vn/tim-kiem-viec-lam-nhanh?is_cv_optional=1" class="quick-link special2">✅ Việc không cần CV</a>
      </div> -->
    </div>

    <div style="width:300px;flex-shrink:0">
      <div class="hero-login-card">
        <p>Đăng ký để trở thành thành viên của cổng thông tin việc làm Trường Cao đẳng Kon Tum</p>
        <span>Tìm việc nhanh hơn, tìm ứng viên phù hợp và nhiều ưu tiên khác!.</span>
        <!-- <div class="btn-google">
          <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M16.51 8H8.98v3h4.3c-.18 1-.74 1.48-1.6 2.04v2.01h2.6a7.8 7.8 0 002.38-5.88c0-.57-.05-.66-.15-1.18z"/><path fill="#34A853" d="M8.98 17c2.16 0 3.97-.72 5.3-1.94l-2.6-2a4.8 4.8 0 01-7.18-2.54H1.83v2.07A8 8 0 008.98 17z"/><path fill="#FBBC05" d="M4.5 10.52a4.8 4.8 0 010-3.04V5.41H1.83a8 8 0 000 7.18l2.67-2.07z"/><path fill="#EA4335" d="M8.98 4.18c1.17 0 2.23.4 3.06 1.2l2.3-2.3A8 8 0 001.83 5.4L4.5 7.49a4.77 4.77 0 014.48-3.31z"/></svg>
          Đăng nhập bằng Google
        </div> -->
        <a href="<?php echo XC_URL;?>/dang-ky-tai-khoan.html"><button class="btn-login-hero">Đăng ký</button></a>
      </div>
    </div>
  </div>
  <div class="hero-slider-dots" id="heroSliderDots" aria-label="Điều hướng slider"></div>
</section>

<script>
  (function () {
    var slider = document.getElementById('heroSlider');
    if (!slider) return;

    var slides = Array.prototype.slice.call(slider.querySelectorAll('.hero-slider-background'));
    var dotsWrap = document.getElementById('heroSliderDots');
    var prev = document.getElementById('heroSliderPrev');
    var next = document.getElementById('heroSliderNext');
    var current = 0;
    var timer = null;
    var interval = 7000;

    function showSlide(index) {
      current = (index + slides.length) % slides.length;
      slides.forEach(function (slide, slideIndex) {
        slide.classList.toggle('active', slideIndex === current);
      });
      Array.prototype.slice.call(dotsWrap.children).forEach(function (dot, dotIndex) {
        var isActive = dotIndex === current;
        dot.classList.toggle('active', isActive);
        dot.setAttribute('aria-current', isActive ? 'true' : 'false');
      });
    }

    function startAutoPlay() {
      window.clearInterval(timer);
      timer = window.setInterval(function () {
        showSlide(current + 1);
      }, interval);
    }

    slides.forEach(function (_, index) {
      var dot = document.createElement('button');
      dot.type = 'button';
      dot.className = index === 0 ? 'hero-slider-dot active' : 'hero-slider-dot';
      dot.setAttribute('aria-label', 'Hiển thị slider ' + (index + 1));
      dot.setAttribute('aria-current', index === 0 ? 'true' : 'false');
      dot.addEventListener('click', function () {
        showSlide(index);
        startAutoPlay();
      });
      dotsWrap.appendChild(dot);
    });

    prev.addEventListener('click', function () {
      showSlide(current - 1);
      startAutoPlay();
    });
    next.addEventListener('click', function () {
      showSlide(current + 1);
      startAutoPlay();
    });
    slider.addEventListener('mouseenter', function () { window.clearInterval(timer); });
    slider.addEventListener('mouseleave', startAutoPlay);
    slider.addEventListener('focusin', function () { window.clearInterval(timer); });
    slider.addEventListener('focusout', startAutoPlay);
    slider.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowLeft') prev.click();
      if (event.key === 'ArrowRight') next.click();
    });

    startAutoPlay();
  }());
</script>
<script>
  (function(){
    var provinceOptions = <?php
      $provinceOptions = array(array('id' => '', 'name' => 'Toàn quốc'));
      foreach($job_provinces as $province){
        $provinceOptions[] = array(
          'id' => (int)$province->id,
          'name' => (string)$province->province_name
        );
      }
      echo json_encode($provinceOptions, JSON_UNESCAPED_UNICODE);
    ?>;
    var originalBtn = document.getElementById('searchLocationBtn');
    var dropdown = document.getElementById('locationDropdown');
    var searchWrap = document.getElementById('heroSearchWrap');
    var heroSlider = document.getElementById('heroSlider');
    var originalSearchInput = document.getElementById('locationSearchInput');
    var listEl = document.getElementById('locationList');
    var emptyEl = document.getElementById('locationEmpty');
    var labelEl = document.getElementById('searchLocationLabel');
    var btn = document.querySelector('.hero-slider .search-btn');
    var input = document.querySelector('.hero-slider .search-input');
    var label = labelEl;
    var provinceMap = <?php
      $provinceMap = array();
      foreach($job_provinces as $province){
        $provinceMap[$province->province_name] = (int)$province->id;
      }
      echo json_encode($provinceMap, JSON_UNESCAPED_UNICODE);
    ?>;
    if(originalBtn && dropdown && originalSearchInput && listEl && emptyEl && labelEl){
      var cleanBtn = originalBtn.cloneNode(true);
      cleanBtn.setAttribute('data-location-initialized', 'true');
      originalBtn.parentNode.replaceChild(cleanBtn, originalBtn);
      var searchInput = originalSearchInput.cloneNode(true);
      originalSearchInput.parentNode.replaceChild(searchInput, originalSearchInput);
      labelEl = document.getElementById('searchLocationLabel');
      label = labelEl;

      var selectedProvince = labelEl.textContent.trim() || 'Toàn quốc';

      function normalizeProvinceKeyword(value){
        var text = String(value || '').toLowerCase();
        if(typeof text.normalize === 'function'){
          text = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        return text.replace(/đ/g, 'd');
      }

      function renderProvinceList(filterValue){
        var keyword = normalizeProvinceKeyword(filterValue).trim();
        var filtered = provinceOptions.filter(function(item){
          return !keyword || normalizeProvinceKeyword(item.name).indexOf(keyword) !== -1;
        });

        listEl.innerHTML = '';
        emptyEl.hidden = filtered.length > 0;

        filtered.forEach(function(item){
          var li = document.createElement('li');
          var optionBtn = document.createElement('button');
          optionBtn.type = 'button';
          optionBtn.setAttribute('data-province-id', item.id || '');
          optionBtn.innerHTML = '<i class="ti ti-map-pin"></i> ' + item.name;
          if(item.name === selectedProvince){
            optionBtn.classList.add('active');
          }
          optionBtn.addEventListener('click', function(event){
            event.preventDefault();
            event.stopPropagation();
            selectedProvince = item.name;
            labelEl.textContent = item.name;
            closeProvinceDropdown();
          });
          li.appendChild(optionBtn);
          listEl.appendChild(li);
        });
      }

      function openProvinceDropdown(){
        dropdown.classList.add('open');
        cleanBtn.classList.add('open');
        cleanBtn.setAttribute('aria-expanded', 'true');
        dropdown.setAttribute('aria-hidden', 'false');
        if(searchWrap){ searchWrap.classList.add('location-open'); }
        if(heroSlider){ heroSlider.classList.add('location-dropdown-open'); }
        searchInput.value = '';
        renderProvinceList('');
        window.setTimeout(function(){ searchInput.focus(); }, 0);
      }

      function closeProvinceDropdown(){
        dropdown.classList.remove('open');
        cleanBtn.classList.remove('open');
        cleanBtn.setAttribute('aria-expanded', 'false');
        dropdown.setAttribute('aria-hidden', 'true');
        if(searchWrap){ searchWrap.classList.remove('location-open'); }
        if(heroSlider){ heroSlider.classList.remove('location-dropdown-open'); }
      }

      cleanBtn.addEventListener('click', function(event){
        event.preventDefault();
        event.stopPropagation();
        if(dropdown.classList.contains('open')){ closeProvinceDropdown(); }
        else { openProvinceDropdown(); }
      });

      cleanBtn.addEventListener('keydown', function(event){
        if(event.key === 'Enter' || event.key === ' '){
          event.preventDefault();
          if(dropdown.classList.contains('open')){ closeProvinceDropdown(); }
          else { openProvinceDropdown(); }
        }
      });

      searchInput.addEventListener('click', function(event){
        event.stopPropagation();
      });
      searchInput.addEventListener('input', function(){
        renderProvinceList(searchInput.value);
      });
      dropdown.addEventListener('click', function(event){
        event.stopPropagation();
      });
      document.addEventListener('click', function(event){
        if(!dropdown.contains(event.target) && !cleanBtn.contains(event.target) && dropdown.classList.contains('open')){
          closeProvinceDropdown();
        }
      });
      document.addEventListener('keydown', function(event){
        if(event.key === 'Escape' && dropdown.classList.contains('open')){
          closeProvinceDropdown();
        }
      });

      renderProvinceList('');
    }

    function goSearch(){
      var params = new URLSearchParams();
      var keyword = input ? input.value.trim() : '';
      var provinceName = label ? label.textContent.trim() : '';
      if(keyword){ params.set('keyword', keyword); }
      if(provinceName && provinceMap[provinceName]){ params.set('province_id', provinceMap[provinceName]); }
      window.location.href = '<?php echo XC_URL; ?>/quan-ly-viec-lam.html' + (params.toString() ? '?' + params.toString() : '');
    }
    if(btn){ btn.addEventListener('click', goSearch); }
    if(input){
      input.addEventListener('keydown', function(event){
        if(event.key === 'Enter'){ event.preventDefault(); goSearch(); }
      });
    }
  })();
</script>

<!-- INDUSTRY TABS -->
<!-- <div class="industry-bar">
  <div class="industry-bar-inner">
    <div class="ind-tab active">Bán sỉ - Bán lẻ - Quản lý cửa hàng</div>
    <div class="ind-tab">Bán hàng - Kinh doanh</div>
    <div class="ind-tab">Marketing</div>
    <div class="ind-tab">Khoa học - Kỹ thuật</div>
    <div class="ind-tab">Kiểm toán</div>
    <div class="ind-more">Tất cả các ngành &rsaquo;</div>
  </div>
</div> -->

<!-- BANNER -->
<div class="banner-section">
  <a href="#" class="banner-img">
    <img src="https://cdn1.vieclam.vn/images/seeker-banner/2025/11/17/desktop_2580x574.jpg"
         alt="Banner Vieclam"
         onerror="this.parentElement.style.background='linear-gradient(135deg,#0d4e96,#ff8a65)';this.style.display='none'"/>
  </a>
</div>
<!-- VIỆC LÀM NỔI BẬT -->
<style>
  .featured-job-filters {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 20px;
  }
  .featured-filter-field {
    position: relative;
    min-width: 0;
  }
  .featured-filter-field i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #778395;
    font-size: 18px;
    pointer-events: none;
  }
  .featured-filter-field select {
    width: 100%;
    height: 48px;
    border: 1px solid #d7e4f2;
    border-radius: 8px;
    background: #fff;
    color: #263247;
    font-size: 14px;
    font-weight: 650;
    outline: none;
    padding: 0 38px 0 42px;
    appearance: none;
    cursor: pointer;
  }
  .featured-filter-field:after {
    content: "";
    position: absolute;
    right: 16px;
    top: 50%;
    width: 8px;
    height: 8px;
    border-right: 1.8px solid #263247;
    border-bottom: 1.8px solid #263247;
    transform: translateY(-65%) rotate(45deg);
    pointer-events: none;
  }
  .featured-jobs-empty,
  .latest-jobs-empty {
    display: none;
    padding: 24px;
    border: 1px dashed #d7dfe8;
    border-radius: 10px;
    background: #fff;
    color: #667085;
    text-align: center;
  }
  .featured-slide-leave,
  .province-slide-leave,
  .urgent-slide-out-next,
  .urgent-slide-out-prev {
    opacity: 0;
  }
  .featured-slide-leave,
  .province-slide-leave,
  .urgent-slide-out-next {
    transform: translateX(-28px);
    transition: opacity .22s ease, transform .22s ease;
  }
  .urgent-slide-out-prev {
    transform: translateX(28px);
    transition: opacity .22s ease, transform .22s ease;
  }
  .featured-slide-enter,
  .province-slide-enter,
  .urgent-slide-in-next,
  .urgent-slide-in-prev {
    animation-duration: .36s;
    animation-timing-function: cubic-bezier(.22,.61,.36,1);
    animation-fill-mode: both;
  }
  .featured-slide-enter,
  .province-slide-enter,
  .urgent-slide-in-next {
    animation-name: homeSlideInNext;
  }
  .urgent-slide-in-prev {
    animation-name: homeSlideInPrev;
  }
  @keyframes homeSlideInNext {
    from { opacity: 0; transform: translateX(28px); }
    to { opacity: 1; transform: translateX(0); }
  }
  @keyframes homeSlideInPrev {
    from { opacity: 0; transform: translateX(-28px); }
    to { opacity: 1; transform: translateX(0); }
  }
  @media (max-width: 900px) {
    .featured-job-filters {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
  @media (max-width: 560px) {
    .featured-job-filters {
      grid-template-columns: 1fr;
    }
  }
</style>
<section class="section latest-jobs-section">
  <div class="section-inner">
    <div class="section-header">
      <div class="section-title">Việc làm tuyển gấp</div>
      <a href="<?php echo XC_URL; ?>/quan-ly-viec-lam.html?post_type=urgent" class="see-all">Xem tất cả <i class="ti ti-arrow-right"></i></a>
    </div>

    <div class="urgent-jobs-filter" aria-label="Bộ lọc việc làm tuyển gấp">
      <div class="urgent-filter-select" id="urgentFilterSelect">
        <button type="button" class="urgent-filter-toggle" id="urgentFilterToggle" aria-expanded="false" aria-haspopup="listbox">
          <i class="ti ti-filter"></i>
          <span>Lọc theo:</span>
          <strong id="urgentFilterLabel">Lọc theo</strong>
          <i class="ti ti-chevron-down"></i>
        </button>
        <div class="urgent-filter-menu" id="urgentFilterMenu" role="listbox" aria-label="Chọn loại bộ lọc">
          <button type="button" class="urgent-filter-option active" data-filter-type="all" role="option" aria-selected="true">Lọc theo <i class="ti ti-check"></i></button>
          <button type="button" class="urgent-filter-option" data-filter-type="location" role="option" aria-selected="false">Địa điểm <i class="ti ti-check"></i></button>
          <button type="button" class="urgent-filter-option" data-filter-type="salary" role="option" aria-selected="false">Mức lương <i class="ti ti-check"></i></button>
          <button type="button" class="urgent-filter-option" data-filter-type="experience" role="option" aria-selected="false">Kinh nghiệm <i class="ti ti-check"></i></button>
          <button type="button" class="urgent-filter-option" data-filter-type="industry" role="option" aria-selected="false">Ngành nghề <i class="ti ti-check"></i></button>
        </div>
      </div>
      <button type="button" class="urgent-filter-nav prev" id="urgentSalaryPrev" aria-label="Cuộn bộ lọc sang trái"><i class="ti ti-chevron-left"></i></button>
      <div class="urgent-filter-chips" id="urgentSalaryChips">
        <button type="button" class="urgent-filter-chip active" data-filter-value="all">Tất cả</button>
      </div>
      <button type="button" class="urgent-filter-nav next" id="urgentSalaryNext" aria-label="Cuộn bộ lọc sang phải"><i class="ti ti-chevron-right"></i></button>
      <label class="mobile-filter-value">
        <i class="ti ti-map-pin" id="urgentMobileFilterIcon"></i>
        <select id="urgentMobileFilterValue" aria-label="Giá trị lọc việc làm tuyển gấp"></select>
      </label>
    </div>

    <div class="jobs-grid" id="urgentJobsGrid" data-total-pages="<?php echo (int)$urgent_jobs_total_pages; ?>" data-server-pagination="true">
      <?php foreach($urgent_jobs as $job){ homeJobCard($job, 'urgent-job-card', false, true); } ?>
    </div>
    <div class="urgent-jobs-empty" id="urgentJobsEmpty">Không có việc làm tuyển gấp phù hợp với bộ lọc đã chọn.</div>
    <div class="jobs-pagination" id="urgentJobsPagination" aria-label="Phân trang việc làm tuyển gấp">
      <button type="button" class="jobs-nav jobs-nav-prev" id="urgentJobsPrev" aria-label="Trang trước"><i class="ti ti-chevron-left"></i></button>
      <div class="jobs-dots-wrap" id="urgentJobsDots"></div>
      <button type="button" class="jobs-nav jobs-nav-next" id="urgentJobsNext" aria-label="Trang sau"><i class="ti ti-chevron-right"></i></button>
    </div>
  </div>
</section>

<!-- VIỆC LÀM TẠI TỈNH -->
<section class="section province-jobs-section">
  <div class="section-inner">
    <div class="section-header">
      <div class="section-title">Việc làm tại tỉnh</div>
      <a href="<?php echo XC_URL; ?>/quan-ly-viec-lam.html?keyword=&province_id=22&job_category_id=&salary_id=&work_type=&post_type=" class="see-all">Xem tất cả <i class="ti ti-arrow-right"></i></a>
    </div>

    <div class="province-jobs-layout">
      <a href="<?php echo XC_URL; ?>/quan-ly-viec-lam.html?keyword=&province_id=22&job_category_id=&salary_id=&work_type=&post_type=" class="province-jobs-banner" aria-label="Khám phá việc làm tại tỉnh">
        <img src="<?php echo XC_URL; ?>/template/frontend/assets/images/banner_doc_1.png"
             alt="Khám phá việc làm tại tỉnh"
             onerror="this.parentElement.style.background='linear-gradient(135deg,#0d4e96,#ff8a65)';this.style.display='none'"/>
      </a>

      <div class="province-jobs-content">
        <div class="province-jobs-grid" id="provinceJobsGrid" data-total-pages="<?php echo (int)$province_jobs_total_pages; ?>">
          <?php foreach($province_jobs as $job){ homeJobCard($job, 'province-job-card', true); } ?>
        </div>
        <div class="jobs-pagination province-jobs-pagination" id="provinceJobsPagination" aria-label="Phân trang việc làm tại tỉnh">
          <button type="button" class="jobs-nav jobs-nav-prev" id="provinceJobsPrev" aria-label="Trang trước"><i class="ti ti-chevron-left"></i></button>
          <div class="jobs-dots-wrap" id="provinceJobsDots"></div>
          <button type="button" class="jobs-nav jobs-nav-next" id="provinceJobsNext" aria-label="Trang sau"><i class="ti ti-chevron-right"></i></button>
        </div>
        <div class="province-jobs-empty" id="provinceJobsEmpty">Chưa có việc làm theo tỉnh phù hợp.</div>
      </div>
    </div>
  </div>
</section>

<script>
  (function () {
    var grid = document.getElementById('provinceJobsGrid');
    var pagination = document.getElementById('provinceJobsPagination');
    var dots = document.getElementById('provinceJobsDots');
    var previous = document.getElementById('provinceJobsPrev');
    var next = document.getElementById('provinceJobsNext');
    var empty = document.getElementById('provinceJobsEmpty');

    if (!grid) return;

    var currentPage = 1;
    var totalPages = parseInt(grid.getAttribute('data-total-pages'), 10) || 1;
    var isLoading = false;

    function setProvinceHtml(html) {
      grid.classList.add('province-slide-leave');
      window.setTimeout(function () {
        grid.innerHTML = html;
        grid.classList.remove('province-slide-leave');
        grid.classList.add('province-slide-enter');
        window.setTimeout(function () {
          grid.classList.remove('province-slide-enter');
        }, 360);
      }, 220);
    }

    function visibleDotPages() {
      if (totalPages <= 3) {
        var allPages = [];
        for (var page = 1; page <= totalPages; page++) allPages.push(page);
        return allPages;
      }
      if (currentPage === 1) return [1, 2, 3];
      if (currentPage === totalPages) return [totalPages - 2, totalPages - 1, totalPages];
      return [currentPage - 1, currentPage, currentPage + 1];
    }

    function renderDots() {
      if (!dots) return;
      dots.innerHTML = '';
      visibleDotPages().forEach(function (page) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = page === currentPage ? 'job-page-dot active' : 'job-page-dot';
        dot.setAttribute('aria-label', 'Trang ' + page);
        dot.setAttribute('aria-current', page === currentPage ? 'page' : 'false');
        dot.addEventListener('click', function () {
          if (page !== currentPage) loadProvinceJobs(page);
        });
        dots.appendChild(dot);
      });
    }

    function updateControls() {
      if (pagination) pagination.style.display = totalPages > 1 ? 'flex' : 'none';
      if (previous) previous.disabled = currentPage <= 1 || isLoading;
      if (next) next.disabled = currentPage >= totalPages || isLoading;
      renderDots();
    }

    function loadProvinceJobs(page) {
      if (isLoading) return;
      isLoading = true;
      updateControls();

      fetch('<?php echo XC_URL; ?>/api/homeProvinceJobs?page=' + encodeURIComponent(page), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (response) { return response.json(); })
        .then(function (result) {
          if (!result || Number(result.status) !== 200) throw new Error('Không thể tải dữ liệu');
          currentPage = parseInt(result.page, 10) || 1;
          totalPages = parseInt(result.total_pages, 10) || 1;
          setProvinceHtml(result.html || '');
          if (empty) empty.style.display = result.html ? 'none' : 'block';
        })
        .catch(function () {
          if (empty) empty.style.display = 'block';
        })
        .finally(function () {
          isLoading = false;
          updateControls();
        });
    }

    if (previous) {
      previous.addEventListener('click', function () {
        if (currentPage > 1) loadProvinceJobs(currentPage - 1);
      });
    }
    if (next) {
      next.addEventListener('click', function () {
        if (currentPage < totalPages) loadProvinceJobs(currentPage + 1);
      });
    }

    updateControls();
  })();
</script>

<!-- VIEC LAM TUYEN GAP -->
<style>
  .urgent-jobs-filter {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 16px;
    overflow: visible;
    position: relative;
    z-index: 30;
  }
  .urgent-filter-select {
    position: relative;
    width: 250px;
    max-width: 100%;
    flex-shrink: 0;
  }
  .urgent-filter-toggle {
    width: 100%;
    height: 38px;
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 0 11px;
    border: 1px solid #c8ddf2;
    border-radius: 8px;
    background: #fff;
    color: #6b7280;
    cursor: pointer;
    transition: border-color 0.18s ease, box-shadow 0.18s ease;
  }
  .urgent-filter-select.open .urgent-filter-toggle,
  .urgent-filter-toggle:hover {
    border-color: #0d4e96;
    box-shadow: 0 5px 14px rgba(13, 78, 150, 0.10);
  }
  .urgent-filter-toggle i {
    color: #0d4e96;
    font-size: 17px;
  }
  .urgent-filter-toggle span {
    font-size: 13px;
    white-space: nowrap;
  }
  .urgent-filter-toggle strong {
    color: #263247;
    font-size: 13px;
    font-weight: 700;
  }
  .urgent-filter-toggle .ti-chevron-down {
    margin-left: auto;
    color: #263247;
    font-size: 15px;
  }
  .urgent-filter-menu {
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 6px);
    z-index: 20;
    display: none;
    padding: 6px;
    border: 1px solid #0d4e96;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 12px 28px rgba(13, 78, 150, 0.14);
  }
  .urgent-filter-select.open .urgent-filter-menu {
    display: block;
  }
  .urgent-filter-option {
    width: 100%;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: 0;
    border-radius: 8px;
    padding: 0 11px;
    background: #fff;
    color: #111827;
    font-size: 13px;
    font-weight: 650;
    text-align: left;
    cursor: pointer;
  }
  .urgent-filter-option.active {
    background: #eef6ff;
    color: #0d4e96;
  }
  .urgent-filter-option i {
    display: none;
    color: #0d4e96;
    font-size: 17px;
  }
  .urgent-filter-option.active i {
    display: inline-block;
  }
  .urgent-filter-nav {
    width: 36px;
    height: 36px;
    border: 0;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #0d4e96;
    background: #eef6ff;
    cursor: pointer;
    flex-shrink: 0;
  }
  .urgent-filter-nav.next {
    color: #0d4e96;
    background: #fff;
    border: 1px solid #0d4e96;
  }
  .urgent-filter-chips {
    display: flex;
    align-items: center;
    gap: 9px;
    overflow-x: auto;
    scroll-behavior: smooth;
    scrollbar-width: none;
    flex: 1;
  }
  .urgent-filter-chips::-webkit-scrollbar {
    display: none;
  }
  .urgent-filter-chip {
    min-width: max-content;
    height: 38px;
    padding: 0 17px;
    border: 1px solid #e5edf5;
    border-radius: 999px;
    background: #fff;
    color: #263247;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
  }
  .urgent-filter-chip:hover {
    transform: translateY(-1px);
  }
  .urgent-filter-chip.active {
    border-color: #0d4e96;
    background: #0d4e96;
    color: #fff;
    box-shadow: 0 7px 16px rgba(13, 78, 150, 0.16);
  }
  .urgent-jobs-empty {
    display: none;
    padding: 24px;
    border: 1px dashed #d7dfe8;
    border-radius: 10px;
    background: #fff;
    color: #667085;
    text-align: center;
  }
  .mobile-filter-value {
    position: relative;
    display: none;
    flex: 1;
    min-width: 0;
  }
  .mobile-filter-value i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 1;
    color: #7f8da0;
    font-size: 18px;
    pointer-events: none;
  }
  .mobile-filter-value select {
    width: 100%;
    height: 38px;
    border: 1px solid #bfd7f0;
    border-radius: 8px;
    background: #fff;
    color: #263247;
    font-size: 13px;
    font-weight: 650;
    outline: none;
    padding: 0 34px 0 38px;
    appearance: none;
  }
  .mobile-filter-value:after {
    content: "";
    position: absolute;
    right: 14px;
    top: 50%;
    width: 8px;
    height: 8px;
    border-right: 1.7px solid #263247;
    border-bottom: 1.7px solid #263247;
    transform: translateY(-65%) rotate(45deg);
    pointer-events: none;
  }
  @media (max-width: 900px) {
    .urgent-jobs-filter {
      align-items: stretch;
      flex-wrap: wrap;
    }
    .urgent-filter-select {
      width: 100%;
    }
    .urgent-filter-chips {
      order: 3;
      flex-basis: 100%;
    }
  }
  @media (max-width: 560px) {
    .urgent-jobs-filter {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
      gap: 10px;
    }
    .urgent-filter-select {
      width: auto;
    }
    .urgent-filter-toggle {
      height: 42px;
      padding: 0 11px;
    }
    .urgent-filter-toggle span {
      display: none;
    }
    .urgent-filter-toggle strong {
      font-size: 14px;
    }
    .urgent-filter-toggle i {
      font-size: 18px;
    }
    .urgent-filter-chips,
    .urgent-filter-nav {
      display: none;
    }
    .mobile-filter-value {
      display: block;
    }
    .mobile-filter-value select {
      height: 42px;
      font-size: 14px;
    }
    .urgent-filter-chip {
      height: 44px;
      padding: 0 18px;
      font-size: 14px;
    }
  }
</style>
<section class="section urgent-jobs-section">
  <div class="section-inner">
    <div class="section-header">
      <div class="section-title">Việc làm mới nhất</div>
      <a href="<?php echo XC_URL; ?>/quan-ly-viec-lam.html" class="see-all">Xem tất cả <i class="ti ti-arrow-right"></i></a>
    </div>

    <div class="urgent-jobs-filter" aria-label="Bộ lọc việc làm mới nhất">
      <div class="urgent-filter-select" id="latestFilterSelect">
        <button type="button" class="urgent-filter-toggle" id="latestFilterToggle" aria-expanded="false" aria-haspopup="listbox">
          <i class="ti ti-filter"></i>
          <span>Lọc theo:</span>
          <strong id="latestFilterLabel">Lọc theo</strong>
          <i class="ti ti-chevron-down"></i>
        </button>
        <div class="urgent-filter-menu" id="latestFilterMenu" role="listbox" aria-label="Chọn loại bộ lọc">
          <button type="button" class="urgent-filter-option active" data-filter-type="all" role="option" aria-selected="true">Lọc theo <i class="ti ti-check"></i></button>
          <button type="button" class="urgent-filter-option" data-filter-type="location" role="option" aria-selected="false">Địa điểm <i class="ti ti-check"></i></button>
          <button type="button" class="urgent-filter-option" data-filter-type="salary" role="option" aria-selected="false">Mức lương <i class="ti ti-check"></i></button>
          <button type="button" class="urgent-filter-option" data-filter-type="experience" role="option" aria-selected="false">Kinh nghiệm <i class="ti ti-check"></i></button>
          <button type="button" class="urgent-filter-option" data-filter-type="industry" role="option" aria-selected="false">Ngành nghề <i class="ti ti-check"></i></button>
        </div>
      </div>
      <button type="button" class="urgent-filter-nav prev" id="latestFilterPrev" aria-label="Cuộn bộ lọc sang trái"><i class="ti ti-chevron-left"></i></button>
      <div class="urgent-filter-chips" id="latestFilterChips">
        <button type="button" class="urgent-filter-chip active" data-filter-value="all">Tất cả</button>
      </div>
      <button type="button" class="urgent-filter-nav next" id="latestFilterNext" aria-label="Cuộn bộ lọc sang phải"><i class="ti ti-chevron-right"></i></button>
      <label class="mobile-filter-value">
        <i class="ti ti-map-pin" id="latestMobileFilterIcon"></i>
        <select id="latestMobileFilterValue" aria-label="Giá trị lọc việc làm mới nhất"></select>
      </label>
    </div>

    <div class="jobs-grid" id="latestJobsGrid" data-total-pages="<?php echo (int)$featured_jobs_total_pages; ?>" data-server-pagination="true">
      <?php foreach($featured_jobs as $job){ homeJobCard($job, '', true); } ?>
    </div>
    <div class="latest-jobs-empty" id="latestJobsEmpty">Không có việc làm mới nhất phù hợp với bộ lọc đã chọn.</div>
    <div class="jobs-pagination" id="latestJobsPagination" aria-label="Phân trang việc làm mới nhất">
      <button type="button" class="jobs-nav jobs-nav-prev" id="latestJobsPrev" aria-label="Trang trước"><i class="ti ti-chevron-left"></i></button>
      <div class="jobs-dots-wrap" id="latestJobsDots"></div>
      <button type="button" class="jobs-nav jobs-nav-next" id="latestJobsNext" aria-label="Trang sau"><i class="ti ti-chevron-right"></i></button>
    </div>
  </div>
</section>

<script>
  (function () {
    window.initHomeFilterJobs = function (config) {
      var grid = document.getElementById(config.gridId);
      var empty = document.getElementById(config.emptyId);
      var pagination = document.getElementById(config.paginationId);
      var dots = document.getElementById(config.dotsId);
      var previous = document.getElementById(config.previousId);
      var next = document.getElementById(config.nextId);
      var filterSelect = document.getElementById(config.filterSelectId);
      var filterToggle = document.getElementById(config.filterToggleId);
      var filterLabel = document.getElementById(config.filterLabelId);
      var filterOptions = Array.prototype.slice.call(document.querySelectorAll('#' + config.filterSelectId + ' [data-filter-type]'));
      var chips = document.getElementById(config.chipsId);
      var chipPrevious = document.getElementById(config.chipPreviousId);
      var chipNext = document.getElementById(config.chipNextId);
      var mobileValue = document.getElementById(config.mobileValueId);
      var mobileIcon = document.getElementById(config.mobileIconId);

      if (!grid) return;

      var currentPage = 1;
      var totalPages = parseInt(grid.getAttribute('data-total-pages'), 10) || 1;
      var activeType = 'all';
      var activeValue = 'all';
      var isLoading = false;
      var filterLabels = { all: 'Lọc theo', location: 'Địa điểm', salary: 'Mức lương', experience: 'Kinh nghiệm', industry: 'Ngành nghề' };
      var filterIcons = { all: 'ti ti-filter', location: 'ti ti-map-pin', salary: 'ti ti-cash', experience: 'ti ti-user-check', industry: 'ti ti-briefcase' };
      var filterValues = config.filterValues || { all: [{ value: 'all', label: 'Tất cả' }] };

      function dotPages() {
        if (totalPages <= 3) {
          var pages = [];
          for (var page = 1; page <= totalPages; page++) pages.push(page);
          return pages;
        }
        if (currentPage <= 1) return [1, 2, 3];
        if (currentPage >= totalPages) return [totalPages - 2, totalPages - 1, totalPages];
        return [currentPage - 1, currentPage, currentPage + 1];
      }

      function renderPagination() {
        if (pagination) pagination.style.display = totalPages > 1 ? 'flex' : 'none';
        if (previous) previous.disabled = currentPage <= 1 || isLoading;
        if (next) next.disabled = currentPage >= totalPages || isLoading;
        if (!dots) return;
        dots.innerHTML = '';
        dotPages().forEach(function (page) {
          var dot = document.createElement('button');
          dot.type = 'button';
          dot.className = page === currentPage ? 'job-page-dot active' : 'job-page-dot';
          dot.setAttribute('aria-label', 'Trang ' + page);
          dot.setAttribute('aria-current', page === currentPage ? 'page' : 'false');
          dot.addEventListener('click', function () {
            if (page !== currentPage) loadJobs(page);
          });
          dots.appendChild(dot);
        });
      }

      function renderFilterValues() {
        if (!chips) return;
        var values = filterValues[activeType] || filterValues.all || [{ value: 'all', label: 'Tất cả' }];
        chips.innerHTML = '';
        if (mobileValue) mobileValue.innerHTML = '';
        if (mobileIcon) mobileIcon.className = filterIcons[activeType] || filterIcons.all;

        values.forEach(function (item) {
          var chip = document.createElement('button');
          chip.type = 'button';
          chip.className = item.value === activeValue ? 'urgent-filter-chip active' : 'urgent-filter-chip';
          chip.textContent = item.label;
          chip.addEventListener('click', function () {
            if (activeValue === item.value) return;
            activeValue = item.value;
            loadJobs(1);
          });
          chips.appendChild(chip);

          if (mobileValue) {
            var option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.label;
            option.selected = item.value === activeValue;
            mobileValue.appendChild(option);
          }
        });
      }

      function setJobsHtml(html, direction) {
        return new Promise(function (resolve) {
          var outClass = direction === 'previous' ? 'urgent-slide-out-prev' : 'urgent-slide-out-next';
          var inClass = direction === 'previous' ? 'urgent-slide-in-prev' : 'urgent-slide-in-next';
          grid.classList.remove('urgent-slide-in-next', 'urgent-slide-in-prev', 'featured-slide-enter', 'featured-slide-leave');
          grid.classList.add(outClass);
          window.setTimeout(function () {
            grid.innerHTML = html;
            grid.classList.remove(outClass);
            grid.classList.add(inClass);
            window.setTimeout(function () {
              grid.classList.remove(inClass);
              resolve();
            }, 350);
          }, 220);
        });
      }

      function loadJobs(page) {
        if (isLoading) return;
        var direction = page < currentPage ? 'previous' : 'next';
        isLoading = true;
        renderPagination();
        var url = config.apiUrl + '?page=' + encodeURIComponent(page) + '&filter_type=' + encodeURIComponent(activeType) + '&filter_value=' + encodeURIComponent(activeValue);
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (response) { return response.json(); })
          .then(function (result) {
            if (!result || Number(result.status) !== 200) throw new Error('Không thể tải dữ liệu');
            currentPage = parseInt(result.page, 10) || 1;
            totalPages = parseInt(result.total_pages, 10) || 1;
            if (empty) empty.style.display = result.html ? 'none' : 'block';
            return setJobsHtml(result.html || '', direction);
          })
          .catch(function () {
            if (empty) empty.style.display = 'block';
          })
          .finally(function () {
            isLoading = false;
            renderPagination();
          });
      }

      if (filterToggle && filterSelect) {
        filterToggle.addEventListener('click', function () {
          var isOpen = filterSelect.classList.toggle('open');
          filterToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
      }
      filterOptions.forEach(function (option) {
        option.addEventListener('click', function () {
          activeType = option.getAttribute('data-filter-type') || 'all';
          activeValue = 'all';
          if (filterLabel) filterLabel.textContent = filterLabels[activeType] || filterLabels.all;
          filterOptions.forEach(function (item) {
            var isActive = item === option;
            item.classList.toggle('active', isActive);
            item.setAttribute('aria-selected', isActive ? 'true' : 'false');
          });
          if (filterSelect) filterSelect.classList.remove('open');
          if (filterToggle) filterToggle.setAttribute('aria-expanded', 'false');
          renderFilterValues();
          loadJobs(1);
        });
      });
      document.addEventListener('click', function (event) {
        if (filterSelect && !filterSelect.contains(event.target)) {
          filterSelect.classList.remove('open');
          if (filterToggle) filterToggle.setAttribute('aria-expanded', 'false');
        }
      });
      if (chipPrevious && chips) chipPrevious.addEventListener('click', function () { chips.scrollBy({ left: -220, behavior: 'smooth' }); });
      if (chipNext && chips) chipNext.addEventListener('click', function () { chips.scrollBy({ left: 220, behavior: 'smooth' }); });
      if (mobileValue) mobileValue.addEventListener('change', function () { activeValue = mobileValue.value; loadJobs(1); });
      if (previous) previous.addEventListener('click', function () { if (currentPage > 1) loadJobs(currentPage - 1); });
      if (next) next.addEventListener('click', function () { if (currentPage < totalPages) loadJobs(currentPage + 1); });

      if (empty) {
        empty.style.display = grid.children.length ? 'none' : 'block';
      }
      renderFilterValues();
      renderPagination();
    };

    window.initHomeFilterJobs({
      gridId: 'latestJobsGrid',
      emptyId: 'latestJobsEmpty',
      paginationId: 'latestJobsPagination',
      dotsId: 'latestJobsDots',
      previousId: 'latestJobsPrev',
      nextId: 'latestJobsNext',
      filterSelectId: 'latestFilterSelect',
      filterToggleId: 'latestFilterToggle',
      filterLabelId: 'latestFilterLabel',
      chipsId: 'latestFilterChips',
      chipPreviousId: 'latestFilterPrev',
      chipNextId: 'latestFilterNext',
      mobileValueId: 'latestMobileFilterValue',
      mobileIconId: 'latestMobileFilterIcon',
      apiUrl: '<?php echo XC_URL; ?>/api/homeFeaturedJobs',
      filterValues: <?php echo $featured_job_filters_json ?: '{"all":[{"value":"all","label":"Tất cả"}],"location":[{"value":"all","label":"Tất cả"}],"salary":[{"value":"all","label":"Tất cả"}],"experience":[{"value":"all","label":"Tất cả"}],"industry":[{"value":"all","label":"Tất cả"}]}'; ?>
    });
  })();
</script>

<script>
  (function () {
    var chipsWrap = document.getElementById('urgentSalaryChips');
    var urgentGrid = document.getElementById('urgentJobsGrid');
    var emptyState = document.getElementById('urgentJobsEmpty');
    var prevBtn = document.getElementById('urgentSalaryPrev');
    var nextBtn = document.getElementById('urgentSalaryNext');
    var urgentJobsPrev = document.getElementById('urgentJobsPrev');
    var urgentJobsNext = document.getElementById('urgentJobsNext');
    var urgentJobsDots = document.getElementById('urgentJobsDots');
    var urgentJobsPagination = document.getElementById('urgentJobsPagination');
    var urgentFilterSelect = document.getElementById('urgentFilterSelect');
    var urgentFilterToggle = document.getElementById('urgentFilterToggle');
    var urgentFilterLabel = document.getElementById('urgentFilterLabel');
    var urgentMobileFilterValue = document.getElementById('urgentMobileFilterValue');
    var urgentMobileFilterIcon = document.getElementById('urgentMobileFilterIcon');
    var urgentFilterOptions = Array.prototype.slice.call(document.querySelectorAll('#urgentFilterSelect .urgent-filter-option'));

    if (!chipsWrap || !urgentGrid || urgentGrid.getAttribute('data-server-pagination') === 'true') return;

    var chips = [];
    var cards = Array.prototype.slice.call(urgentGrid.querySelectorAll('.urgent-job-card'));
    var activeFilterType = 'all';
    var activeFilterValue = 'all';
    var urgentPage = 0;
    var urgentPageSize = 3;
    var urgentFilterLabels = {
      all: 'Lọc theo',
      location: 'Địa điểm',
      salary: 'Mức lương',
      experience: 'Kinh nghiệm',
      industry: 'Ngành nghề'
    };
    var urgentFilterIcons = {
      all: 'ti ti-filter',
      location: 'ti ti-map-pin',
      salary: 'ti ti-cash',
      experience: 'ti ti-user-check',
      industry: 'ti ti-briefcase'
    };
    var urgentChipSets = {
      all: [{ value: 'all', label: 'Tất cả' }],
      location: [
        { value: 'all', label: 'Tất cả' },
        { value: 'hanoi', label: 'Hà Nội' },
        { value: 'tphcm', label: 'TP.HCM' },
        { value: 'danang', label: 'Đà Nẵng' },
        { value: 'binhduong', label: 'Bình Dương' },
        { value: 'cantho', label: 'Cần Thơ' }
      ],
      salary: [
        { value: 'all', label: 'Tất cả' },
        { value: '1-3', label: '1 - 3 triệu' },
        { value: '3-5', label: '3 - 5 triệu' },
        { value: '5-7', label: '5 - 7 triệu' },
        { value: '7-10', label: '7 - 10 triệu' },
        { value: '10-15', label: '10 - 15 triệu' },
        { value: '15-20', label: '15 - 20 triệu' }
      ],
      experience: [
        { value: 'all', label: 'Tất cả' },
        { value: 'none', label: 'Chưa có kinh nghiệm' },
        { value: '1-2', label: '1 - 2 năm' },
        { value: '3-5', label: '3 - 5 năm' },
        { value: '5+', label: 'Trên 5 năm' }
      ],
      industry: [
        { value: 'all', label: 'Tất cả' },
        { value: 'sales', label: 'Bán hàng - Kinh doanh' },
        { value: 'logistics', label: 'Kho vận - Logistics' },
        { value: 'it', label: 'CNTT - Phần mềm' },
        { value: 'service', label: 'Chăm sóc khách hàng' },
        { value: 'hr', label: 'Nhân sự' }
      ]
    };

    function getUrgentFilteredCards() {
      return cards.filter(function (card) {
        return activeFilterType === 'all' || activeFilterValue === 'all' || card.getAttribute('data-' + activeFilterType) === activeFilterValue;
      });
    }

    function renderUrgentChips() {
      var items = urgentChipSets[activeFilterType] || [];
      chipsWrap.innerHTML = '';
      if (urgentMobileFilterValue) {
        urgentMobileFilterValue.innerHTML = '';
      }
      if (urgentMobileFilterIcon) {
        urgentMobileFilterIcon.className = urgentFilterIcons[activeFilterType] || 'ti ti-filter';
      }

      items.forEach(function (item) {
        var chip = document.createElement('button');
        chip.type = 'button';
        chip.className = item.value === activeFilterValue ? 'urgent-filter-chip active' : 'urgent-filter-chip';
        chip.setAttribute('data-filter-value', item.value);
        chip.textContent = item.label;
        chip.addEventListener('click', function () {
          activeFilterValue = item.value;
          urgentPage = 0;
          chips.forEach(function (currentChip) {
            currentChip.classList.toggle('active', currentChip === chip);
          });
          renderUrgentJobs();
        });
        chipsWrap.appendChild(chip);

        if (urgentMobileFilterValue) {
          var option = document.createElement('option');
          option.value = item.value;
          option.textContent = item.label;
          option.selected = item.value === activeFilterValue;
          urgentMobileFilterValue.appendChild(option);
        }
      });

      chips = Array.prototype.slice.call(chipsWrap.querySelectorAll('.urgent-filter-chip'));
      chipsWrap.scrollLeft = 0;
    }

    function renderUrgentPagination(totalPages) {
      if (!urgentJobsDots) return;
      while (urgentJobsDots.firstChild) {
        urgentJobsDots.removeChild(urgentJobsDots.firstChild);
      }

      for (var i = 0; i < totalPages; i++) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'job-page-dot';
        dot.setAttribute('aria-label', 'Trang ' + (i + 1));
        dot.setAttribute('data-page', i);
        dot.addEventListener('click', function () {
          urgentPage = parseInt(this.getAttribute('data-page'), 10);
          renderUrgentJobs();
        });
        urgentJobsDots.appendChild(dot);
      }
      Array.prototype.slice.call(urgentJobsDots.querySelectorAll('.job-page-dot')).forEach(function (dot, index) {
        var isActive = index === urgentPage;
        dot.classList.toggle('active', isActive);
        dot.setAttribute('aria-current', isActive ? 'page' : 'false');
      });
    }

    function renderUrgentJobs() {
      var filteredCards = getUrgentFilteredCards();
      var totalPages = Math.max(1, Math.ceil(filteredCards.length / urgentPageSize));

      if (urgentPage >= totalPages) urgentPage = totalPages - 1;

      cards.forEach(function (card) {
        card.style.display = 'none';
      });

      filteredCards.forEach(function (card, index) {
        var isOnPage = index >= urgentPage * urgentPageSize && index < (urgentPage + 1) * urgentPageSize;
        card.style.display = isOnPage ? '' : 'none';
      });

      if (emptyState) {
        emptyState.style.display = filteredCards.length ? 'none' : 'block';
      }
      if (urgentJobsPagination) {
        urgentJobsPagination.style.display = filteredCards.length > urgentPageSize ? '' : 'none';
      }
      if (urgentJobsPrev) {
        urgentJobsPrev.disabled = urgentPage === 0;
      }
      if (urgentJobsNext) {
        urgentJobsNext.disabled = urgentPage >= totalPages - 1;
      }

      renderUrgentPagination(filteredCards.length > urgentPageSize ? totalPages : 0);
    }

    if (urgentFilterToggle && urgentFilterSelect) {
      urgentFilterToggle.addEventListener('click', function () {
        var isOpen = urgentFilterSelect.classList.toggle('open');
        urgentFilterToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });
    }

    urgentFilterOptions.forEach(function (option) {
      option.addEventListener('click', function () {
        activeFilterType = option.getAttribute('data-filter-type');
        activeFilterValue = 'all';
        urgentPage = 0;

        if (urgentFilterLabel) {
          urgentFilterLabel.textContent = urgentFilterLabels[activeFilterType] || '';
        }
        urgentFilterOptions.forEach(function (item) {
          var isActive = item === option;
          item.classList.toggle('active', isActive);
          item.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        if (urgentFilterSelect) {
          urgentFilterSelect.classList.remove('open');
        }
        if (urgentFilterToggle) {
          urgentFilterToggle.setAttribute('aria-expanded', 'false');
        }

        renderUrgentChips();
        renderUrgentJobs();
      });
    });

    document.addEventListener('click', function (event) {
      if (urgentFilterSelect && !urgentFilterSelect.contains(event.target)) {
        urgentFilterSelect.classList.remove('open');
        if (urgentFilterToggle) {
          urgentFilterToggle.setAttribute('aria-expanded', 'false');
        }
      }
    });

    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        chipsWrap.scrollBy({ left: -220, behavior: 'smooth' });
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        chipsWrap.scrollBy({ left: 220, behavior: 'smooth' });
      });
    }
    if (urgentMobileFilterValue) {
      urgentMobileFilterValue.addEventListener('change', function () {
        activeFilterValue = urgentMobileFilterValue.value;
        urgentPage = 0;
        chips.forEach(function (chip) {
          chip.classList.toggle('active', chip.getAttribute('data-filter-value') === activeFilterValue);
        });
        renderUrgentJobs();
      });
    }
    if (urgentJobsPrev) {
      urgentJobsPrev.addEventListener('click', function () {
        if (urgentPage > 0) {
          urgentPage--;
          renderUrgentJobs();
        }
      });
    }
    if (urgentJobsNext) {
      urgentJobsNext.addEventListener('click', function () {
        var totalPages = Math.ceil(getUrgentFilteredCards().length / urgentPageSize);
        if (urgentPage < totalPages - 1) {
          urgentPage++;
          renderUrgentJobs();
        }
      });
    }

    renderUrgentChips();
    renderUrgentJobs();
  })();
</script>

<script>
  (function () {
    if (typeof window.initHomeFilterJobs !== 'function') return;
    window.initHomeFilterJobs({
      gridId: 'urgentJobsGrid',
      emptyId: 'urgentJobsEmpty',
      paginationId: 'urgentJobsPagination',
      dotsId: 'urgentJobsDots',
      previousId: 'urgentJobsPrev',
      nextId: 'urgentJobsNext',
      filterSelectId: 'urgentFilterSelect',
      filterToggleId: 'urgentFilterToggle',
      filterLabelId: 'urgentFilterLabel',
      chipsId: 'urgentSalaryChips',
      chipPreviousId: 'urgentSalaryPrev',
      chipNextId: 'urgentSalaryNext',
      mobileValueId: 'urgentMobileFilterValue',
      mobileIconId: 'urgentMobileFilterIcon',
      apiUrl: '<?php echo XC_URL; ?>/api/homeUrgentJobs',
      filterValues: <?php echo $urgent_job_filters_json ?: '{"all":[{"value":"all","label":"Tất cả"}],"location":[{"value":"all","label":"Tất cả"}],"salary":[{"value":"all","label":"Tất cả"}],"experience":[{"value":"all","label":"Tất cả"}],"industry":[{"value":"all","label":"Tất cả"}]}'; ?>
    });
  })();
</script>


<!-- SINH VIÊN NỔI BẬT -->
<?php
$featuredStudents = [
  ['name' => 'Nguyễn Thị Lan', 'dob' => '12/03/2003', 'major' => 'Kế toán', 'color' => '#0d4e96', 'initials' => 'NL'],
  ['name' => 'Trần Văn Hùng', 'dob' => '05/07/2002', 'major' => 'CNTT', 'color' => '#1565c0', 'initials' => 'TH'],
  ['name' => 'Lê Thị Mai', 'dob' => '20/01/2003', 'major' => 'Marketing', 'color' => '#2e7d32', 'initials' => 'LM'],
  ['name' => 'Phạm Quốc Bảo', 'dob' => '15/09/2002', 'major' => 'Kinh doanh', 'color' => '#c62828', 'initials' => 'PB'],
  ['name' => 'Hoàng Thị Thu', 'dob' => '08/11/2003', 'major' => 'Nhân sự', 'color' => '#6a1b9a', 'initials' => 'HT'],
  ['name' => 'Vũ Minh Khoa', 'dob' => '25/04/2002', 'major' => 'Kỹ thuật', 'color' => '#00695c', 'initials' => 'VK'],
  ['name' => 'Đặng Thị Hoa', 'dob' => '17/06/2003', 'major' => 'Thiết kế', 'color' => '#e65100', 'initials' => 'ĐH'],
  ['name' => 'Bùi Văn Nam', 'dob' => '03/02/2002', 'major' => 'Tài chính', 'color' => '#1a237e', 'initials' => 'BN'],
  ['name' => 'Nguyễn Anh Tuấn', 'dob' => '29/08/2003', 'major' => 'Logistics', 'color' => '#33691e', 'initials' => 'NT'],
  ['name' => 'Trịnh Thị Nga', 'dob' => '11/12/2002', 'major' => 'Du lịch', 'color' => '#880e4f', 'initials' => 'TN'],
  ['name' => 'Phan Quang Vinh', 'dob' => '07/05/2003', 'major' => 'Xây dựng', 'color' => '#4e342e', 'initials' => 'PV'],
  ['name' => 'Lương Thị Linh', 'dob' => '22/10/2002', 'major' => 'Y tế', 'color' => '#00838f', 'initials' => 'LL'],
  ['name' => 'Cao Văn Đức', 'dob' => '14/01/2003', 'major' => 'Luật', 'color' => '#37474f', 'initials' => 'CĐ'],
  ['name' => 'Đinh Thị Hằng', 'dob' => '30/07/2002', 'major' => 'Ngân hàng', 'color' => '#0d4e96', 'initials' => 'ĐH'],
  ['name' => 'Hồ Quốc Toản', 'dob' => '18/03/2003', 'major' => 'Báo chí', 'color' => '#c62828', 'initials' => 'HT'],
  ['name' => 'Mai Thị Tuyết', 'dob' => '09/11/2002', 'major' => 'Ngoại ngữ', 'color' => '#2e7d32', 'initials' => 'MT'],
  ['name' => 'Lê Hoàng Nam', 'dob' => '26/06/2003', 'major' => 'Điện tử', 'color' => '#1565c0', 'initials' => 'LN'],
  ['name' => 'Trần Thị Bình', 'dob' => '02/09/2002', 'major' => 'Môi trường', 'color' => '#388e3c', 'initials' => 'TB'],
  ['name' => 'Ngô Văn Thành', 'dob' => '13/04/2003', 'major' => 'Cơ khí', 'color' => '#5e35b1', 'initials' => 'NT'],
  ['name' => 'Đoàn Thị Phương', 'dob' => '21/02/2002', 'major' => 'Quản trị', 'color' => '#f57f17', 'initials' => 'ĐP'],
  ['name' => 'Võ Minh Trí', 'dob' => '05/12/2003', 'major' => 'CNTT', 'color' => '#00695c', 'initials' => 'VT'],
  ['name' => 'Chu Thị Lan', 'dob' => '19/08/2002', 'major' => 'Kế toán', 'color' => '#ad1457', 'initials' => 'CL'],
  ['name' => 'Dương Văn Long', 'dob' => '27/05/2003', 'major' => 'Kiến trúc', 'color' => '#4a148c', 'initials' => 'DL'],
  ['name' => 'Lý Thị Kim', 'dob' => '08/01/2002', 'major' => 'Marketing', 'color' => '#006064', 'initials' => 'LK'],
  ['name' => 'Huỳnh Văn Phúc', 'dob' => '16/09/2003', 'major' => 'Tài chính', 'color' => '#0d4e96', 'initials' => 'HP'],
  ['name' => 'Nguyễn Thị Yến', 'dob' => '04/03/2002', 'major' => 'Nhân sự', 'color' => '#880e4f', 'initials' => 'NY'],
  ['name' => 'Tô Quang Hải', 'dob' => '23/07/2003', 'major' => 'Logistics', 'color' => '#1b5e20', 'initials' => 'TH'],
  ['name' => 'Kiều Thị Nga', 'dob' => '11/11/2002', 'major' => 'Thiết kế', 'color' => '#bf360c', 'initials' => 'KN'],
  ['name' => 'Bùi Hoàng Minh', 'dob' => '28/04/2003', 'major' => 'Xây dựng', 'color' => '#37474f', 'initials' => 'BM'],
  ['name' => 'Phan Thị Thảo', 'dob' => '06/02/2002', 'major' => 'Du lịch', 'color' => '#00838f', 'initials' => 'PT'],
  ['name' => 'Vương Văn Đạt', 'dob' => '15/10/2003', 'major' => 'Kỹ thuật', 'color' => '#283593', 'initials' => 'VĐ'],
  ['name' => 'Hoàng Thị Liên', 'dob' => '01/06/2002', 'major' => 'Y tế', 'color' => '#c62828', 'initials' => 'HL'],
  ['name' => 'Lưu Minh Quân', 'dob' => '20/08/2003', 'major' => 'Ngân hàng', 'color' => '#2e7d32', 'initials' => 'LQ'],
  ['name' => 'Đặng Thị Oanh', 'dob' => '09/12/2002', 'major' => 'Luật', 'color' => '#6a1b9a', 'initials' => 'ĐO'],
  ['name' => 'Trần Văn Khải', 'dob' => '17/03/2003', 'major' => 'Báo chí', 'color' => '#e65100', 'initials' => 'TK'],
  ['name' => 'Lê Thị Diệu', 'dob' => '03/09/2002', 'major' => 'Ngoại ngữ', 'color' => '#00695c', 'initials' => 'LD'],
];
if(!empty($featured_candidates)){
  $featuredStudents = array();
  foreach($featured_candidates as $candidate){
    $featuredStudents[] = array(
      'name' => homeCandidateName($candidate),
      'dob' => homeCandidateDateText($candidate),
      'major' => homeCandidateMajorText($candidate),
      'color' => homeCandidateAccentColor($candidate),
      'initials' => homeCandidateInitials($candidate),
      'url' => homeCandidateUrl($candidate),
      'user_group' => $candidate->user_group ?? null,
      'avatar' => homeCandidateAvatarUrl($candidate->avatar_url ?? $candidate->user_avatar_url ?? '')
    );
  }
}else{
  $featuredStudents = array();
}
$svPages = array_chunk($featuredStudents, 12);
?>
<section class="sv-section">
  <div class="section-inner">

    <div class="section-header">
      <div class="section-title">Ứng viên nổi bật</div>
      <a href="<?php echo XC_URL; ?>/quan-ly-ung-vien.html" class="see-all">
        Xem tất cả <i class="ti ti-arrow-right"></i>
      </a>
    </div>

    <div id="svSliderWrap">
      <div id="svTrack">
        <?php foreach ($svPages as $pageStudents): ?>
        <div class="sv-grid">
          <?php foreach ($pageStudents as $s): ?>
          <a href="<?= htmlspecialchars($s['url'] ?? '#', ENT_QUOTES, 'UTF-8') ?>" class="sv-card">
            <!-- <span class="sv-badge">Ứng viên</span> -->
            <!-- <span class="sv-badge-xuat-sac">Xuất sắc</span> -->
            <div class="sv-avatar-wrap">
              <?php if (!empty($s['avatar'])): ?>
                <img src="<?= htmlspecialchars($s['avatar'], ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>"
                     class="sv-avatar-photo" loading="lazy"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
              <?php endif; ?>
              <div class="sv-avatar-fallback"
                   style="background:<?= htmlspecialchars($s['color'], ENT_QUOTES, 'UTF-8') ?>;<?= !empty($s['avatar']) ? 'display:none' : '' ?>">
                <?= htmlspecialchars($s['initials'], ENT_QUOTES, 'UTF-8') ?>
              </div>
            </div>

            <div class="sv-name" title="<?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
            </div>

            <div class="sv-dob">
              <i class="ti ti-calendar"></i>
              <?= htmlspecialchars($s['dob'], ENT_QUOTES, 'UTF-8') ?>
            </div>

            <div class="sv-major"><?= htmlspecialchars($s['major'], ENT_QUOTES, 'UTF-8') ?></div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="sv-pagination jobs-pagination" id="svPagination" aria-label="Phân trang sinh viên nổi bật">
      <button type="button" class="jobs-nav jobs-nav-prev" id="svPrev" aria-label="Trang trước">
        <i class="ti ti-chevron-left"></i>
      </button>
      <div class="jobs-dots-wrap" id="svDotsWrap">
        <?php for ($i = 0, $svPageCount = count($svPages); $i < $svPageCount; $i++): ?>
        <button type="button" class="sv-dot<?= $i === 0 ? ' active' : '' ?>" onclick="svGoTo(<?= $i ?>)" aria-label="Trang <?= $i + 1 ?>"></button>
        <?php endfor; ?>
      </div>
      <button type="button" class="jobs-nav jobs-nav-next" id="svNext" aria-label="Trang sau">
        <i class="ti ti-chevron-right"></i>
      </button>
    </div>

  </div>
</section>

<!-- NHÀ TUYỂN DỤNG TIÊU BIỂU -->
<?php $recentEmployerSlides = homeEmployerSliderItems($recent_employers, 12); ?>
<section class="employers-showcase-section">
  <div class="section-inner">
    <div class="section-header">
      <div class="section-title">Nhà tuyển dụng tiêu biểu</div>
      <a href="<?php echo XC_URL; ?>/quan-ly-viec-lam.html" class="see-all">Xem thêm <i class="ti ti-arrow-right"></i></a>
    </div>

    <?php if(!empty($linked_employers)): ?>
      <div class="employer-featured-slider" aria-label="Nhà tuyển dụng đã liên kết">
        <button type="button" class="employer-slider-nav employer-slider-prev" id="linkedEmployersPrev" aria-label="Nhà tuyển dụng trước"><i class="ti ti-chevron-left"></i></button>
        <div class="employer-slider-viewport employer-featured-viewport" id="linkedEmployersViewport">
          <div class="employer-slider-track employer-featured-track" id="linkedEmployersTrack">
            <?php foreach($linked_employers as $employer){ homeEmployerCard($employer); } ?>
          </div>
        </div>
        <button type="button" class="employer-slider-nav employer-slider-next" id="linkedEmployersNext" aria-label="Nhà tuyển dụng tiếp theo"><i class="ti ti-chevron-right"></i></button>
      </div>
    <?php else: ?>
      <div class="employer-showcase-empty">Chưa có nhà tuyển dụng liên kết.</div>
    <?php endif; ?>

    <?php if(!empty($recentEmployerSlides)): ?>
      <div class="employer-slider-viewport employer-logo-viewport" aria-label="Nhà tuyển dụng mới">
        <div class="employer-slider-track employer-logo-track">
          <?php foreach($recentEmployerSlides as $employer){ homeEmployerCard($employer, true); } ?>
          <div class="employer-slider-copy" aria-hidden="true">
            <?php foreach($recentEmployerSlides as $employer){ homeEmployerCard($employer, true, true); } ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<script>
  (function () {
    var viewport = document.getElementById('linkedEmployersViewport');
    var track = document.getElementById('linkedEmployersTrack');
    var previous = document.getElementById('linkedEmployersPrev');
    var next = document.getElementById('linkedEmployersNext');

    if (!viewport || !track || !previous || !next) return;

    var currentPage = 0;

    function getState() {
      var cards = track.querySelectorAll('.employer-featured-card');
      var firstCard = cards[0];
      if (!firstCard) return { cards: 0, perPage: 1, totalPages: 1, cardWidth: 0 };
      var gap = parseFloat(window.getComputedStyle(track).gap) || 0;
      var cardWidth = firstCard.getBoundingClientRect().width + gap;
      var perPage = Math.max(1, Math.floor((viewport.clientWidth + gap) / cardWidth));
      return {
        cards: cards.length,
        perPage: perPage,
        totalPages: Math.max(1, Math.ceil(cards.length / perPage)),
        cardWidth: cardWidth
      };
    }

    function render() {
      var state = getState();
      if (currentPage >= state.totalPages) currentPage = state.totalPages - 1;
      track.style.transform = 'translateX(-' + (currentPage * state.perPage * state.cardWidth) + 'px)';
      previous.disabled = currentPage <= 0;
      next.disabled = currentPage >= state.totalPages - 1;
    }

    previous.addEventListener('click', function () {
      if (currentPage > 0) {
        currentPage--;
        render();
      }
    });
    next.addEventListener('click', function () {
      var state = getState();
      if (currentPage < state.totalPages - 1) {
        currentPage++;
        render();
      }
    });
    window.addEventListener('resize', render);
    render();
  })();
</script>
<!-- VIỆC LÀM THEO NGHỀ NGHIỆP -->
<section class="section" style="background:#f4f5f6">
  <div class="section-inner">
    <div class="section-header">
      <div class="section-title">Việc làm theo nghề nghiệp</div>
      <a href="<?php echo XC_URL; ?>/quan-ly-viec-lam.html" class="see-all">Xem thêm <i class="ti ti-arrow-right"></i></a>
    </div>
    <div class="cats-grid">
      <?php foreach($job_categories_with_counts as $category): ?>
        <?php $categoryIcon = trim((string)($category->job_category_icon ?? '')) ?: 'ti ti-briefcase'; ?>
        <a href="<?php echo XC_URL; ?>/quan-ly-viec-lam.html?job_category_id=<?php echo (int)$category->id; ?>" class="cat-card" aria-label="Xem việc làm ngành <?php echo homeJobH($category->job_category_name); ?>">
          <div class="cat-icon-wrap"><i class="<?php echo homeJobH($categoryIcon); ?>"></i></div>
          <div class="cat-name"><?php echo homeJobH($category->job_category_name); ?></div>
          <div class="cat-count"><?php echo number_format((int)$category->published_jobs, 0, ',', '.'); ?> việc làm</div>
        </a>
      <?php endforeach; ?>
      <a href="<?php echo XC_URL; ?>/quan-ly-viec-lam.html" class="cat-card" style="border-style:dashed;background:#fafafa">
        <div class="cat-icon-wrap" style="background:#f5f5f5"><i class="ti ti-plus"></i></div>
        <div class="cat-name" style="color:#0d4e96">Xem tất cả</div>
        <div class="cat-count">Các ngành nghề</div>
      </a>
    </div>
  </div>
</section>

<!-- TIN TUC NOI BAT -->
<style>
  .featured-news-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
  }
  .featured-news-card {
    display: block;
    overflow: hidden;
    border: 1px solid #e8edf3;
    border-radius: 10px;
    background: #fff;
    text-decoration: none;
    color: inherit;
    box-shadow: 0 6px 18px rgba(13, 78, 150, 0.06);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
  }
  .featured-news-card:hover {
    transform: translateY(-3px);
    border-color: #c9dcf0;
    box-shadow: 0 12px 28px rgba(13, 78, 150, 0.12);
  }
  .featured-news-thumb {
    width: 100%;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    background: #eef3f8;
  }
  .featured-news-thumb img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    transition: transform 0.25s ease;
  }
  .featured-news-card:hover .featured-news-thumb img {
    transform: scale(1.04);
  }
  .featured-news-body {
    padding: 14px 15px 16px;
  }
  .featured-news-title {
    min-height: 44px;
    color: #16324f;
    font-size: 16px;
    font-weight: 750;
    line-height: 1.38;
  }
  .featured-news-desc {
    margin-top: 8px;
    min-height: 42px;
    color: #5f6f80;
    font-size: 13px;
    line-height: 1.55;
  }
  .featured-news-date {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
    color: #8a98a8;
    font-size: 12px;
    font-weight: 600;
  }
  .featured-news-date i {
    font-size: 14px;
    color: #0d4e96;
  }
  @media (max-width: 900px) {
    .featured-news-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
  @media (max-width: 560px) {
    .featured-news-grid {
      grid-template-columns: 1fr;
    }
    .featured-news-title,
    .featured-news-desc {
      min-height: auto;
    }
  }
</style>
<section class="section" style="background:#fff">
  <div class="section-inner">
    <div class="section-header">
      <div class="section-title">Tin tức nổi bật</div>
      <a href="<?php echo homeJobH(XC_URL.'/tin-tuc-su-kien.html'); ?>" class="see-all">Xem tất cả <i class="ti ti-arrow-right"></i></a>
    </div>
    <div class="featured-news-grid">
      <?php if(!empty($home_featured_news)): ?>
        <?php foreach($home_featured_news as $news): ?>
          <?php $newsTitle = homeNewsTitle($news); ?>
          <a href="<?php echo homeJobH(homeNewsUrl($news)); ?>" class="featured-news-card">
            <div class="featured-news-thumb">
              <img src="<?php echo homeJobH(homeNewsImageUrl($news->event_image ?? '')); ?>" alt="<?php echo homeJobH($newsTitle); ?>" loading="lazy">
            </div>
            <div class="featured-news-body">
              <div class="featured-news-title"><?php echo homeJobH($newsTitle); ?></div>
              <div class="featured-news-desc"><?php echo homeJobH(homeNewsExcerpt($news, 150)); ?></div>
              <div class="featured-news-date"><i class="ti ti-calendar"></i> <?php echo homeJobH(homeNewsDateText($news->event_created_date ?? '')); ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="grid-column:1 / -1; padding:24px 0; text-align:center; color:#667085;">Chưa có tin tức nổi bật.</div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php
// YouTube helpers for video parsing
if (!function_exists('getYoutubeVideoId')) {
    function getYoutubeVideoId($url) {
        if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|[^/]+/\?v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match)) {
            return $match[1];
        }
        return '';
    }
}

if (!function_exists('getYoutubeEmbedUrl')) {
    function getYoutubeEmbedUrl($url) {
        $videoId = getYoutubeVideoId($url);
        return $videoId ? 'https://www.youtube.com/embed/' . $videoId : $url;
    }
}

if (!function_exists('getYoutubeThumbnailUrl')) {
    function getYoutubeThumbnailUrl($url) {
        $videoId = getYoutubeVideoId($url);
        return $videoId ? 'https://img.youtube.com/vi/' . $videoId . '/hqdefault.jpg' : '';
    }
}

$videos_list = isset($home_videos) && is_array($home_videos) ? $home_videos : array();
if (!empty($videos_list)):
    $featured_video = $videos_list[0];
    $featured_thumb = getYoutubeThumbnailUrl($featured_video->video_url);
    $featured_embed = getYoutubeEmbedUrl($featured_video->video_url);
?>
<style>
  /* Video Section Styles */
  .video-section-dark {
    background: linear-gradient(135deg, #1c3047 0%, #223a53 52%, #294564 100%);
    color: #f8fafc;
    padding: 60px 0;
  }
  .video-section-dark .section-title {
    color: #f8fbff !important;
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 8px;
  }
  .video-section-subtitle {
    color: #d2ddeb;
    font-size: 15px;
    font-weight: 400;
  }
  .video-layout-grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 30px;
    margin-top: 30px;
  }
  .video-featured-column {
    grid-column: span 8;
  }
  .video-list-column {
    grid-column: span 4;
    display: flex;
    flex-direction: column;
  }
  
  /* Featured Video Card */
  .video-featured-card-wrapper {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.4s cubic-bezier(0.165, 0.84, 0.44, 1), box-shadow 0.4s ease, border-color 0.4s ease;
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: 0 16px 36px rgba(8, 23, 43, 0.22);
    height: 100%;
    display: flex;
    flex-direction: column;
    backdrop-filter: blur(10px);
  }
  .video-featured-card-wrapper:hover {
    transform: translateY(-6px);
    box-shadow: 0 24px 44px rgba(11, 50, 96, 0.24);
    border-color: rgba(147, 197, 253, 0.4);
    background: rgba(255, 255, 255, 0.14);
  }
  .video-featured-img-container {
    position: relative;
    width: 100%;
    aspect-ratio: 16/9;
    overflow: hidden;
    background: #000;
  }
  .video-featured-img-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s ease;
  }
  .video-featured-card-wrapper:hover .video-featured-img-container img {
    transform: scale(1.03);
  }
  .video-featured-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(to bottom, rgba(14, 28, 45, 0.02) 45%, rgba(14, 28, 45, 0.82) 100%);
    z-index: 1;
  }
  .video-featured-badge {
    position: absolute;
    top: 16px;
    left: 16px;
    background: #f97316;
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    z-index: 2;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 10px rgba(249, 115, 22, 0.35);
  }
  .video-play-btn-pulse {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 70px;
    height: 70px;
    background: rgba(37, 99, 235, 0.9);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    z-index: 2;
    box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.6);
    animation: pulsePlay 2s infinite;
    transition: all 0.3s ease;
  }
  .video-play-btn-pulse i {
    margin-left: 4px;
  }
  @keyframes pulsePlay {
    0% {
      transform: translate(-50%, -50%) scale(0.95);
      box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.7);
    }
    70% {
      transform: translate(-50%, -50%) scale(1);
      box-shadow: 0 0 0 20px rgba(37, 99, 235, 0);
    }
    100% {
      transform: translate(-50%, -50%) scale(0.95);
      box-shadow: 0 0 0 0 rgba(37, 99, 235, 0);
    }
  }
  .video-featured-card-wrapper:hover .video-play-btn-pulse {
    background: #2563eb;
    transform: translate(-50%, -50%) scale(1.08);
  }
  .video-featured-info {
    padding: 24px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }
  .video-featured-title {
    color: #ffffff;
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 12px;
    line-height: 1.4;
    transition: color 0.3s;
  }
  .video-featured-card-wrapper:hover .video-featured-title {
    color: #bfdbfe;
  }
  .video-featured-desc {
    color: #d7e2ee;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 16px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
  .video-meta {
    display: flex;
    align-items: center;
    font-size: 13px;
    color: #bfd0e0;
  }
  .video-meta span {
    display: flex;
    align-items: center;
    gap: 6px;
  }

  /* Video Vertical Slider (List) */
  .video-slider-viewport {
    height: 480px;
    overflow: hidden;
    position: relative;
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.08);
    padding: 10px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
    backdrop-filter: blur(10px);
  }
  .video-slider-viewport::before,
  .video-slider-viewport::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    height: 40px;
    z-index: 3;
    pointer-events: none;
  }
  .video-slider-viewport::before {
    top: 0;
    background: linear-gradient(to bottom, #1f344c 0%, rgba(31, 52, 76, 0) 100%);
  }
  .video-slider-viewport::after {
    bottom: 0;
    background: linear-gradient(to top, #1f344c 0%, rgba(31, 52, 76, 0) 100%);
  }
  
  .video-slider-track {
    display: flex;
    flex-direction: column;
    gap: 16px;
    animation: scrollUpList 25s linear infinite;
  }
  .video-slider-viewport:hover .video-slider-track {
    animation-play-state: paused;
  }
  
  @keyframes scrollUpList {
    0% {
      transform: translateY(0);
    }
    100% {
      transform: translateY(-50%);
    }
  }
  
  .video-slider-item {
    background: rgba(255, 255, 255, 0.06);
    border-radius: 12px;
    padding: 12px;
    display: flex;
    gap: 12px;
    align-items: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(255, 255, 255, 0.08);
  }
  .video-slider-item:hover {
    background: rgba(255, 255, 255, 0.12);
    transform: translateX(4px);
    border-color: rgba(147, 197, 253, 0.32);
    box-shadow: 0 10px 18px rgba(12, 31, 56, 0.18);
  }
  .video-item-thumb {
    position: relative;
    width: 100px;
    height: 60px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
    background: #000;
  }
  .video-item-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .video-item-play-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 24px;
    height: 24px;
    background: rgba(37, 99, 235, 0.9);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 10px;
    opacity: 0.8;
    transition: all 0.3s ease;
  }
  .video-slider-item:hover .video-item-play-icon {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1.15);
    background: #2563eb;
  }
  .video-item-details {
    flex-grow: 1;
    min-width: 0;
  }
  .video-item-title {
    color: #ffffff;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 4px;
    line-height: 1.4;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .video-slider-item:hover .video-item-title {
    color: #60a5fa;
  }
  .video-item-desc {
    color: #64748b;
    font-size: 11px;
    margin-bottom: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.4;
  }
  .video-item-date {
    color: #475569;
    font-size: 10px;
    display: block;
  }
  
  /* Modal Player Styles */
  .video-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 99999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    transition: opacity 0.3s ease;
    opacity: 0;
  }
  .video-modal.show {
    display: flex;
    opacity: 1;
  }
  .video-modal-content {
    background: #1e293b;
    border-radius: 20px;
    width: 100%;
    max-width: 900px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    overflow: hidden;
    position: relative;
    border: 1px solid rgba(255, 255, 255, 0.08);
    transform: scale(0.9);
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  }
  .video-modal.show .video-modal-content {
    transform: scale(1);
  }
  .video-modal-close {
    position: absolute;
    top: 16px;
    right: 16px;
    background: rgba(15, 23, 42, 0.6);
    color: #94a3b8;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    font-size: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    z-index: 10;
  }
  .video-modal-close:hover {
    background: #ef4444;
    color: #fff;
    transform: rotate(90deg);
  }
  .video-modal-body {
    padding: 0;
  }
  .video-iframe-container {
    position: relative;
    width: 100%;
    aspect-ratio: 16/9;
    background: #000;
  }
  .video-iframe-container iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
  }
  .video-modal-info {
    padding: 20px 24px 24px;
  }
  .video-modal-title {
    color: #f8fafc;
    font-size: 18px;
    font-weight: 600;
    line-height: 1.4;
    margin: 0;
  }
  
  /* Responsive Queries */
  @media (max-width: 992px) {
    .video-featured-column,
    .video-list-column {
      grid-column: span 12;
    }
    .video-slider-viewport {
      height: 380px;
    }
  }
</style>

<section class="section video-section-dark">
  <div class="section-inner">
    <div class="section-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.08); padding-bottom: 16px; margin-bottom: 24px; display: block;">
      <div class="section-title">Chuyên mục Video</div>
      <div class="video-section-subtitle">Tổng hợp video hướng dẫn, chia sẻ kinh nghiệm tìm việc & tuyển dụng thực tế</div>
    </div>
    
    <div class="video-layout-grid">
      <!-- Cột 8: Video mới nhất -->
      <div class="video-featured-column">
        <div class="video-featured-card-wrapper" onclick="openVideoModal('<?php echo homeJobH($featured_embed); ?>', '<?php echo homeJobH($featured_video->video_name); ?>')">
          <div class="video-featured-img-container">
            <img src="<?php echo homeJobH($featured_thumb); ?>" alt="<?php echo homeJobH($featured_video->video_name); ?>" loading="lazy">
            <div class="video-featured-overlay"></div>
            <div class="video-play-btn-pulse">
              <i class="ti ti-player-play-filled"></i>
            </div>
            <div class="video-featured-badge">Mới nhất</div>
          </div>
          <div class="video-featured-info">
            <h3 class="video-featured-title"><?php echo homeJobH($featured_video->video_name); ?></h3>
            <p class="video-featured-desc"><?php echo homeJobH($featured_video->video_description); ?></p>
            <div class="video-meta">
              <span><i class="ti ti-calendar"></i> <?php echo date('d/m/Y', strtotime($featured_video->video_created_at)); ?></span>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Cột 4: Danh sách video cuộn dọc slider -->
      <div class="video-list-column">
        <div class="video-slider-viewport">
          <div class="video-slider-track">
            <?php 
            // Loop twice to ensure infinite scrolling
            for ($loop = 0; $loop < 2; $loop++): 
                foreach ($videos_list as $vid): 
                    $vid_thumb = getYoutubeThumbnailUrl($vid->video_url);
                    $vid_embed = getYoutubeEmbedUrl($vid->video_url);
            ?>
              <div class="video-slider-item" onclick="openVideoModal('<?php echo homeJobH($vid_embed); ?>', '<?php echo homeJobH($vid->video_name); ?>')">
                <div class="video-item-thumb">
                  <img src="<?php echo homeJobH($vid_thumb); ?>" alt="<?php echo homeJobH($vid->video_name); ?>" loading="lazy">
                  <div class="video-item-play-icon"><i class="ti ti-player-play"></i></div>
                </div>
                <div class="video-item-details">
                  <h4 class="video-item-title"><?php echo homeJobH($vid->video_name); ?></h4>
                  <p class="video-item-desc"><?php echo homeJobH($vid->video_description); ?></p>
                  <span class="video-item-date"><?php echo date('d/m/Y', strtotime($vid->video_created_at)); ?></span>
                </div>
              </div>
            <?php 
                endforeach;
            endfor; 
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Modal Player -->
<div id="videoModal" class="video-modal" onclick="closeVideoModal(event)">
  <div class="video-modal-content">
    <button class="video-modal-close" onclick="closeVideoModal(event)">&times;</button>
    <div class="video-modal-body">
      <div class="video-iframe-container">
        <iframe id="videoPlayerIframe" src="" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
      </div>
      <div class="video-modal-info">
        <h3 id="videoModalTitle" class="video-modal-title"></h3>
      </div>
    </div>
  </div>
</div>

<script>
function openVideoModal(embedUrl, title) {
  const modal = document.getElementById('videoModal');
  const iframe = document.getElementById('videoPlayerIframe');
  const modalTitle = document.getElementById('videoModalTitle');
  
  // Set autoplay parameter
  const autoplayUrl = embedUrl + (embedUrl.includes('?') ? '&' : '?') + 'autoplay=1';
  iframe.src = autoplayUrl;
  modalTitle.textContent = title;
  
  modal.classList.add('show');
  document.body.style.overflow = 'hidden'; // prevent background scrolling
}

function closeVideoModal(event) {
  // If event is click on background or close button
  if (!event || event.target.id === 'videoModal' || event.target.classList.contains('video-modal-close') || event.target.nodeName === 'BUTTON') {
    const modal = document.getElementById('videoModal');
    const iframe = document.getElementById('videoPlayerIframe');
    
    iframe.src = ''; // Stop video play
    modal.classList.remove('show');
    document.body.style.overflow = ''; // restore scrolling
  }
}

// Add Escape key handler to close modal
document.addEventListener('keydown', function(event) {
  if (event.key === 'Escape') {
    const modal = document.getElementById('videoModal');
    if (modal && modal.classList.contains('show')) {
      closeVideoModal();
    }
  }
});
</script>
<?php endif; ?>

<!-- CTA EMPLOYER BANNER -->
<div class="cta-banner">
  <div class="cta-inner">
    <div class="cta-text">
      <h2>Bạn là Nhà Tuyển Dụng?</h2>
      <p>Đăng tin tuyển dụng miễn phí, tiếp cận hơn 5 triệu ứng viên tiềm năng. Quản lý hồ sơ ứng tuyển dễ dàng và hiệu quả.</p>
    </div>
    <div class="cta-stats">
      <!-- <div class="cta-stat">
        <div class="cta-stat-num">450K+</div>
        <div class="cta-stat-label">Nhà tuyển dụng</div>
      </div>
      <div class="cta-stat">
        <div class="cta-stat-num">5M+</div>
        <div class="cta-stat-label">Ứng viên</div>
      </div>
      <div class="cta-stat">
        <div class="cta-stat-num">1.2M+</div>
        <div class="cta-stat-label">Tin tuyển dụng</div>
      </div> -->
    </div>
    <!-- <button class="btn-cta">Đăng tin tuyển dụng ngay →</button> -->
  </div>
</div>

<!-- APP DOWNLOAD -->
<!-- <section class="app-section">
  <div class="app-inner">
    <div class="app-text">
      <h2>Tải app Vieclam – Ứng tuyển 1 chạm!</h2>
      <p>Tìm việc làm nhanh hơn, quản lý hồ sơ tiện lợi hơn. Nhận thông báo việc làm phù hợp mọi lúc, mọi nơi.</p>
      <div class="app-badges">
        <div class="app-badge">
          <i class="ti ti-brand-google-play"></i>
          <div class="app-badge-text">
            <span class="small">Tải trên</span>
            <span class="big">Google Play</span>
          </div>
        </div>
        <div class="app-badge">
          <i class="ti ti-brand-apple"></i>
          <div class="app-badge-text">
            <span class="small">Tải trên</span>
            <span class="big">App Store</span>
          </div>
        </div>
      </div>
    </div>
    <div class="app-qr" style="width:100px;height:100px">
      <div style="text-align:center;font-size:11px;color:#aaa;padding:8px">
        <i class="ti ti-qrcode" style="font-size:36px;display:block;margin-bottom:4px;color:#ccc"></i>
        QR tải app
      </div>
    </div>
  </div>
</section> -->

<?php require "footer.php"; ?>
