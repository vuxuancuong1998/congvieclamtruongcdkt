<?php require "header.php"; ?>
<?php
$candidate = isset($candidate_detail) && is_object($candidate_detail) ? $candidate_detail : (object) array();
$experiences = isset($candidate_detail_experiences) && is_array($candidate_detail_experiences) ? $candidate_detail_experiences : array();
$certificates = isset($candidate_detail_certificates) && is_array($candidate_detail_certificates) ? $candidate_detail_certificates : array();
$relatedCandidates = isset($related_candidates) && is_array($related_candidates) ? $related_candidates : array();

function candidateDetailH($value){
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function candidateDetailText($value, $fallback = 'Đang cập nhật'){
  $value = trim((string)$value);
  return $value !== '' ? $value : $fallback;
}

function candidateDetailDate($value, $fallback = 'Đang cập nhật'){
  $time = $value ? strtotime((string)$value) : false;
  return $time ? date('d/m/Y', $time) : $fallback;
}

function candidateDetailMonthYear($value, $fallback = 'Đang cập nhật'){
  $time = $value ? strtotime((string)$value) : false;
  return $time ? date('m/Y', $time) : $fallback;
}

function candidateDetailGenderLabel($value){
  $value = strtolower(trim((string)$value));
  $map = array(
    '1' => 'Nam',
    '2' => 'Nữ',
    '3' => 'Khác',
    'male' => 'Nam',
    'female' => 'Nữ',
    'other' => 'Khác',
    'nam' => 'Nam',
    'nu' => 'Nữ',
    'nữ' => 'Nữ',
    'khac' => 'Khác',
    'khác' => 'Khác',
  );
  return isset($map[$value]) ? $map[$value] : 'Đang cập nhật';
}

function candidateDetailDegreeLabel($value){
  $value = strtolower(trim((string)$value));
  $map = array(
    'trung_cap' => 'Trung cấp',
    'cao_dang' => 'Cao đẳng',
    'dai_hoc' => 'Đại học',
    'sau_dai_hoc' => 'Sau đại học',
    'thac_si' => 'Thạc sĩ',
    'tien_si' => 'Tiến sĩ',
    'trung cấp' => 'Trung cấp',
    'cao đẳng' => 'Cao đẳng',
    'đại học' => 'Đại học',
    'sau đại học' => 'Sau đại học',
    'thạc sĩ' => 'Thạc sĩ',
    'tiến sĩ' => 'Tiến sĩ',
  );
  return isset($map[$value]) ? $map[$value] : candidateDetailText($value);
}

function candidateDetailWorkTypeLabel($value){
  $value = strtolower(trim((string)$value));
  $map = array(
    'any' => 'Thỏa thuận',
    'full_time' => 'Full-time',
    'full-time' => 'Full-time',
    'part_time' => 'Part-time',
    'part-time' => 'Part-time',
    'remote' => 'Remote',
    'hybrid' => 'Hybrid',
    'internship' => 'Thực tập',
    'freelance' => 'Freelance',
  );
  return isset($map[$value]) ? $map[$value] : candidateDetailText($value);
}

function candidateDetailAvatarUrl($value){
  $value = trim((string)$value);
  if($value === ''){ return ''; }
  if(preg_match('#^(https?:)?//#i', $value) || strpos($value, 'data:') === 0){ return $value; }
  return XC_URL.'/'.ltrim($value, '/');
}

function candidateDetailInitials($name){
  $name = trim((string)$name);
  if($name === ''){ return 'UV'; }
  $parts = preg_split('/\s+/', $name);
  $letters = '';
  foreach((array)$parts as $part){
    if($part !== ''){ $letters .= mb_substr($part, 0, 1, 'UTF-8'); }
    if(mb_strlen($letters, 'UTF-8') >= 2){ break; }
  }
  return mb_strtoupper($letters ?: mb_substr($name, 0, 2, 'UTF-8'), 'UTF-8');
}

function candidateDetailAccentColor($seed){
  $palette = array('#0d4e96', '#1565c0', '#2e7d32', '#c62828', '#6a1b9a', '#00695c', '#e65100', '#1a237e', '#00838f', '#37474f');
  return $palette[((int)$seed) % count($palette)];
}

function candidateDetailSkills($value){
  $value = trim((string)$value);
  if($value === ''){ return array(); }
  $decoded = json_decode($value, true);
  if(is_array($decoded)){
    $skills = array();
    foreach($decoded as $item){
      $item = trim((string)$item);
      if($item !== ''){ $skills[] = $item; }
    }
    return array_values(array_unique($skills));
  }
  $parts = preg_split('/[\r\n,;|]+/u', $value);
  $skills = array();
  foreach((array)$parts as $item){
    $item = trim((string)$item);
    if($item !== ''){ $skills[] = $item; }
  }
  return array_values(array_unique($skills));
}

function candidateDetailFileUrl($value){
  $value = trim((string)$value);
  if($value === ''){ return ''; }
  if(preg_match('#^(https?:)?//#i', $value) || strpos($value, 'data:') === 0){ return $value; }
  return XC_URL.'/'.ltrim($value, '/');
}

function candidateDetailFileName($value){
  $value = trim((string)$value);
  if($value === ''){ return 'CV đang cập nhật'; }
  $path = parse_url($value, PHP_URL_PATH);
  $path = $path !== null ? $path : $value;
  $name = basename($path);
  return $name !== '' ? $name : 'CV đang cập nhật';
}

function candidateDetailExperiencePeriod($start, $end){
  $startText = candidateDetailMonthYear($start);
  $endText = $end ? candidateDetailMonthYear($end) : 'Hiện tại';
  return $startText.' - '.$endText;
}

function candidateDetailUrl($candidate){
  return general::getInstance()->permalink((int)($candidate->id ?? 0), 'candidate_profile');
}

$candidateName = candidateDetailText($candidate->full_name ?? '', 'Ứng viên');
$candidateAvatar = candidateDetailAvatarUrl($candidate->avatar_url ?? '');
$candidatePhone = candidateDetailText($candidate->phone ?? ($candidate->user_phone ?? ''));
$candidateEmail = candidateDetailText($candidate->user_email ?? '');
$candidateAddress = candidateDetailText($candidate->address_detail ?? ($candidate->province_name ?? ''));
$candidateProvince = candidateDetailText($candidate->province_name ?? '');
$desiredProvince = candidateDetailText($candidate->desired_province_name ?? '');
$desiredSalary = candidateDetailText($candidate->salary_name ?? '');
$desiredPosition = candidateDetailText($candidate->desired_position ?? ($candidate->job_category_name ?? ''), 'Ứng viên đang tìm việc');
$candidateMajor = candidateDetailText($candidate->job_category_name ?? '');
$candidateDegree = candidateDetailDegreeLabel($candidate->degree ?? '');
$candidateWorkType = candidateDetailWorkTypeLabel($candidate->desired_work_type ?? '');
$candidateBirthDate = candidateDetailDate($candidate->date_of_birth ?? '');
$candidateGender = candidateDetailGenderLabel($candidate->gender ?? '');
$candidateSchool = candidateDetailText($candidate->school_name ?? '');
$candidateGraduationYear = candidateDetailText($candidate->graduation_year ?? '');
$candidateSkills = candidateDetailSkills($candidate->soft_skills ?? '');
$candidateCareerGoal = nl2br(candidateDetailH(candidateDetailText($candidate->career_goal ?? '', 'Ứng viên chưa cập nhật mục tiêu nghề nghiệp.')));
$cvUrl = candidateDetailFileUrl($candidate->cv_url ?? '');
$cvFileName = candidateDetailFileName($candidate->cv_url ?? '');
$cvUploadedAt = candidateDetailDate($candidate->cv_uploaded_at ?? '', 'Đang cập nhật');
$breadcrumbUrl = XC_URL.'/quan-ly-ung-vien.html';
?>

<style>
.candidate-page{background:#f4f5f6;padding:24px 20px 36px;overflow:hidden}
.candidate-page *{box-sizing:border-box}
.cd-container{width:100%;max-width:1280px;margin:0 auto}
.cd-breadcrumb{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;color:#777;font-size:13px;line-height:1.5}
.cd-breadcrumb a{color:#0d4e96;font-weight:700}
.cd-hero{position:relative;overflow:hidden;padding:26px;border-radius:20px;background:linear-gradient(135deg,#0d4e96,#155fae 55%,#844404);color:#fff}
.cd-hero:after{position:absolute;top:-70px;right:-70px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.12);content:""}
.cd-hero-inner{position:relative;z-index:1;display:flex;align-items:center;gap:20px}
.cd-avatar{display:flex;flex:0 0 110px;width:110px;height:110px;align-items:center;justify-content:center;overflow:hidden;border:4px solid rgba(255,255,255,.35);border-radius:24px;background:#fff;color:#fff;font-size:36px;font-weight:800}
.cd-hero h1{margin:0 0 6px;font-size:28px;line-height:1.3;color:#fff;overflow-wrap:anywhere}
.cd-position{margin-bottom:12px;font-size:15px;line-height:1.55}
.cd-tags,.cd-actions,.cd-skill-list,.cd-file-actions,.cd-candidate-meta{display:flex;flex-wrap:wrap;gap:8px}
.cd-tag{padding:6px 10px;border:1px solid rgba(255,255,255,.28);border-radius:999px;background:rgba(255,255,255,.16);font-size:12px;font-weight:700;line-height:1.35}
.cd-actions{margin-top:18px;gap:10px}
.cd-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:11px 18px;border:1px solid transparent;border-radius:12px;font-size:14px;font-weight:800;line-height:1.4;text-align:center;cursor:pointer}
.cd-btn-outline{border-color:rgba(255,255,255,.35);background:rgba(255,255,255,.12);color:#fff}
.cd-btn[disabled]{cursor:not-allowed;opacity:.7}
.cd-layout{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:18px;margin-top:18px}
.cd-main,.cd-sidebar{min-width:0}
.cd-card{padding:20px;border:1px solid #e9eef5;border-radius:18px;background:#fff;box-shadow:0 4px 18px rgba(13,78,150,.06)}
.cd-card+.cd-card{margin-top:14px}
.cd-title{display:flex;align-items:center;gap:8px;margin:0 0 14px;color:#111;font-size:18px;font-weight:800;line-height:1.4}
.cd-title i{color:#0d4e96;font-size:20px}
.cd-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.cd-info{min-width:0;padding:12px;border:1px solid #edf2f7;border-radius:14px;background:#f8fafc}
.cd-label{margin-bottom:5px;color:#888;font-size:11px;line-height:1.4}
.cd-value{color:#222;font-size:13px;font-weight:700;line-height:1.5;overflow-wrap:anywhere}
.cd-content{color:#444;font-size:14px;line-height:1.8;overflow-wrap:anywhere}
.cd-content ul{margin:0;padding-left:18px}.cd-content li{margin-bottom:7px}
.cd-timeline{display:flex;flex-direction:column;gap:14px}
.cd-time-item{min-width:0;padding-left:14px;border-left:3px solid #0d4e96}
.cd-time-item h4{margin:0 0 4px;color:#111;font-size:14px;line-height:1.5;overflow-wrap:anywhere}.cd-time-item span{color:#777;font-size:12px}.cd-time-item p{margin:6px 0 0;color:#555;font-size:13px;line-height:1.7;overflow-wrap:anywhere}
.cd-skill{padding:7px 10px;border:1px solid #dbeafe;border-radius:999px;background:#eef5ff;color:#0d4e96;font-size:12px;font-weight:700;line-height:1.35}
.cd-highlight{padding:14px;border-left:4px solid #0d4e96;border-radius:14px;background:#eef5ff;color:#24415f;font-size:13px;line-height:1.7;overflow-wrap:anywhere}
.cd-file{display:flex;align-items:flex-start;gap:10px;padding:14px;border:1px dashed #b8c7d9;border-radius:14px;background:#f8fafc}.cd-file>div{min-width:0}.cd-file i{flex:0 0 auto;color:#0d4e96;font-size:28px}.cd-file strong{display:block;color:#111;font-size:13px;overflow-wrap:anywhere}.cd-file p{margin:3px 0 0;color:#777;font-size:12px;line-height:1.55}
.cd-download-btn,.cd-preview-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:9px 12px;border-radius:10px;font-size:12px;font-weight:800;line-height:1.4;text-align:center}.cd-download-btn{background:#0d4e96;color:#fff}.cd-preview-btn{border:1px solid #dbeafe;background:#fff;color:#0d4e96}
.cd-featured-candidates{display:flex;flex-direction:column;gap:10px}.cd-candidate-mini{display:flex;align-items:center;gap:10px;min-width:0;padding:10px;border:1px solid #edf2f7;border-radius:14px;background:#f8fafc;color:inherit}.cd-candidate-avatar{display:flex;flex:0 0 46px;width:46px;height:46px;align-items:center;justify-content:center;overflow:hidden;border:3px solid #fff;border-radius:50%;color:#fff;font-size:15px;font-weight:800;box-shadow:0 2px 8px rgba(13,78,150,.18)}.cd-candidate-info{min-width:0}.cd-candidate-info h4{margin:0 0 3px;color:#111;font-size:13px;line-height:1.35;overflow-wrap:anywhere}.cd-candidate-info p{margin:0;color:#666;font-size:11px;line-height:1.45;overflow-wrap:anywhere}.cd-candidate-meta{gap:5px;margin-top:6px}.cd-candidate-meta span{max-width:100%;padding:3px 7px;border:1px solid #dbeafe;border-radius:999px;background:#fff;color:#0d4e96;font-size:10px;font-weight:700;overflow-wrap:anywhere}
@media (max-width:1024px){.cd-layout{grid-template-columns:1fr}.cd-sidebar{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.cd-sidebar .cd-card{margin-top:0}.cd-sidebar .cd-card:first-child{grid-column:1/-1}}
@media (max-width:768px){.candidate-page{padding:18px 14px 28px}.cd-hero{padding:22px}.cd-hero-inner{align-items:flex-start;flex-direction:column;gap:14px}.cd-avatar{flex-basis:88px;width:88px;height:88px;border-radius:20px;font-size:29px}.cd-hero h1{font-size:22px}.cd-position{font-size:14px}.cd-grid{grid-template-columns:1fr}.cd-sidebar{grid-template-columns:1fr}.cd-sidebar .cd-card:first-child{grid-column:auto}.cd-card{padding:16px}.cd-title{font-size:17px}}
@media (max-width:480px){.candidate-page{padding:14px 12px 24px}.cd-breadcrumb{gap:5px;font-size:12px}.cd-hero{padding:18px;border-radius:16px}.cd-tags{gap:6px}.cd-tag{max-width:100%;padding:5px 8px;font-size:11px;overflow-wrap:anywhere}.cd-actions{flex-direction:column}.cd-btn{width:100%}.cd-card{padding:14px;border-radius:14px}.cd-title{font-size:16px}.cd-file{flex-direction:column}.cd-file-actions{flex-direction:column;width:100%}.cd-file-actions a{width:100%}.cd-skill-list{gap:6px}.cd-skill{font-size:11px}.cd-candidate-mini{align-items:flex-start}}
</style>

<main class="candidate-page">
  <div class="cd-container">
    <div class="cd-breadcrumb">
      <a href="<?php echo candidateDetailH(XC_URL); ?>">Trang chủ</a><span>/</span><a href="<?php echo candidateDetailH($breadcrumbUrl); ?>">Ứng viên</a><span>/</span><span>Chi tiết hồ sơ</span>
    </div>

    <section class="cd-hero">
      <div class="cd-hero-inner">
        <?php if($candidateAvatar !== ''){ ?>
          <div class="cd-avatar"><img src="<?php echo candidateDetailH($candidateAvatar); ?>" alt="<?php echo candidateDetailH($candidateName); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:inherit"></div>
        <?php }else{ ?>
          <div class="cd-avatar" style="background:<?php echo candidateDetailH(candidateDetailAccentColor($candidate->id ?? 0)); ?>"><?php echo candidateDetailH(candidateDetailInitials($candidateName)); ?></div>
        <?php } ?>
        <div>
          <h1><?php echo candidateDetailH($candidateName); ?></h1>
          <div class="cd-position"><i class="ti ti-briefcase"></i> Ứng viên vị trí <?php echo candidateDetailH($desiredPosition); ?></div>
          <div class="cd-tags">
            <span class="cd-tag"><?php echo candidateDetailH($candidateGender); ?></span><span class="cd-tag">Sinh ngày: <?php echo candidateDetailH($candidateBirthDate); ?></span><span class="cd-tag"><?php echo candidateDetailH($candidateProvince); ?></span><span class="cd-tag">Mong muốn: <?php echo candidateDetailH($desiredSalary); ?></span>
          </div>
          <div class="cd-actions">
            <!-- <button class="cd-btn cd-btn-primary" type="button"><i class="ti ti-send"></i> Mời ứng tuyển</button> -->
            <?php if($cvUrl !== ''){ ?>
              <a class="cd-btn cd-btn-outline" href="<?php echo candidateDetailH($cvUrl); ?>" target="_blank" rel="noopener"><i class="ti ti-file-cv"></i> Xem CV</a>
            <?php }else{ ?>
              <button class="cd-btn cd-btn-outline" type="button" disabled><i class="ti ti-file-cv"></i> Chưa có CV</button>
            <?php } ?>
            <!-- <button class="cd-btn cd-btn-outline" type="button"><i class="ti ti-heart"></i> Lưu hồ sơ</button> -->
          </div>
        </div>
      </div>
    </section>

    <div class="cd-layout">
      <div class="cd-main">
        <section class="cd-card">
          <h2 class="cd-title"><i class="ti ti-user"></i> Thông tin cá nhân cơ bản</h2>
          <div class="cd-grid">
            <div class="cd-info"><div class="cd-label">Họ và tên</div><div class="cd-value"><?php echo candidateDetailH($candidateName); ?></div></div>
            <div class="cd-info"><div class="cd-label">Ngày sinh</div><div class="cd-value"><?php echo candidateDetailH($candidateBirthDate); ?></div></div>
            <div class="cd-info"><div class="cd-label">Giới tính</div><div class="cd-value"><?php echo candidateDetailH($candidateGender); ?></div></div>
            <div class="cd-info"><div class="cd-label">Địa chỉ hiện tại</div><div class="cd-value"><?php echo candidateDetailH($candidateAddress); ?></div></div>
            <div class="cd-info"><div class="cd-label">Số điện thoại</div><div class="cd-value"><?php echo candidateDetailH($candidatePhone); ?></div></div>
            <div class="cd-info"><div class="cd-label">Email</div><div class="cd-value"><?php echo candidateDetailH($candidateEmail); ?></div></div>
          </div>
        </section>

        <section class="cd-card">
          <h2 class="cd-title"><i class="ti ti-school"></i> Học vấn</h2>
          <div class="cd-grid">
            <div class="cd-info"><div class="cd-label">Bằng cấp</div><div class="cd-value"><?php echo candidateDetailH($candidateDegree); ?></div></div>
            <div class="cd-info"><div class="cd-label">Chuyên ngành</div><div class="cd-value"><?php echo candidateDetailH($candidateMajor); ?></div></div>
            <div class="cd-info"><div class="cd-label">Trường</div><div class="cd-value"><?php echo candidateDetailH($candidateSchool); ?></div></div>
            <div class="cd-info"><div class="cd-label">Năm tốt nghiệp</div><div class="cd-value"><?php echo candidateDetailH($candidateGraduationYear); ?></div></div>
          </div>
        </section>

        <section class="cd-card">
          <h2 class="cd-title"><i class="ti ti-briefcase"></i> Kinh nghiệm làm việc</h2>
          <div class="cd-timeline">
            <?php if(!empty($experiences)){ ?>
              <?php foreach($experiences as $experience){ ?>
                <div class="cd-time-item">
                  <h4><?php echo candidateDetailH(candidateDetailText(($experience->company_name ?? '').' - '.($experience->position ?? ''), 'Đang cập nhật')); ?></h4>
                  <span><?php echo candidateDetailH(candidateDetailExperiencePeriod($experience->start_date ?? '', $experience->end_date ?? '')); ?></span>
                  <p><?php echo candidateDetailH(candidateDetailText($experience->description ?? '', 'Ứng viên chưa cập nhật mô tả công việc.')); ?></p>
                </div>
              <?php } ?>
            <?php }else{ ?>
              <div class="cd-time-item">
                <h4>Chưa có kinh nghiệm được cập nhật</h4>
                <span>Đang cập nhật</span>
                <p>Ứng viên chưa bổ sung thông tin kinh nghiệm làm việc.</p>
              </div>
            <?php } ?>
          </div>
        </section>

        <section class="cd-card">
          <h2 class="cd-title"><i class="ti ti-star"></i> Kỹ năng mềm & chuyên môn</h2>
          <div class="cd-skill-list">
            <?php if(!empty($candidateSkills)){ ?>
              <?php foreach($candidateSkills as $skill){ ?>
                <span class="cd-skill"><?php echo candidateDetailH($skill); ?></span>
              <?php } ?>
            <?php }else{ ?>
              <span class="cd-skill">Đang cập nhật</span>
            <?php } ?>
          </div>
        </section>

        <section class="cd-card">
          <h2 class="cd-title"><i class="ti ti-certificate"></i> Chứng chỉ</h2>
          <div class="cd-content">
            <?php if(!empty($certificates)){ ?>
              <ul>
                <?php foreach($certificates as $certificate){ ?>
                  <li>
                    <?php
                    $certParts = array(candidateDetailText($certificate->cert_name ?? '', 'Chứng chỉ đang cập nhật'));
                    if(trim((string)($certificate->issuer ?? '')) !== ''){ $certParts[] = 'Đơn vị cấp: '.trim((string)$certificate->issuer); }
                    if(!empty($certificate->issued_date)){ $certParts[] = 'Ngày cấp: '.candidateDetailDate($certificate->issued_date); }
                    echo candidateDetailH(implode(' - ', $certParts));
                    ?>
                  </li>
                <?php } ?>
              </ul>
            <?php }else{ ?>
              <p>Ứng viên chưa cập nhật chứng chỉ.</p>
            <?php } ?>
          </div>
        </section>

        <section class="cd-card">
          <h2 class="cd-title"><i class="ti ti-target-arrow"></i> Mục tiêu nghề nghiệp</h2>
          <div class="cd-highlight">
            <?php echo $candidateCareerGoal; ?>
          </div>
        </section>
      </div>

      <aside class="cd-sidebar">
        <section class="cd-card">
          <h2 class="cd-title"><i class="ti ti-filter-check"></i> Tiêu chí mong muốn</h2>
          <div class="cd-grid" style="grid-template-columns:1fr">
            <div class="cd-info"><div class="cd-label">Vị trí muốn ứng tuyển</div><div class="cd-value"><?php echo candidateDetailH($desiredPosition); ?></div></div>
            <div class="cd-info"><div class="cd-label">Mức lương mong đợi</div><div class="cd-value"><?php echo candidateDetailH($desiredSalary); ?></div></div>
            <div class="cd-info"><div class="cd-label">Địa điểm mong muốn</div><div class="cd-value"><?php echo candidateDetailH($desiredProvince); ?></div></div>
            <div class="cd-info"><div class="cd-label">Hình thức làm việc</div><div class="cd-value"><?php echo candidateDetailH($candidateWorkType); ?></div></div>
          </div>
        </section>

        <section class="cd-card">
          <h2 class="cd-title"><i class="ti ti-upload"></i> Hồ sơ đính kèm</h2>

          <div class="cd-file">
            <i class="ti ti-file-type-pdf"></i>
            <div>
              <strong><?php echo candidateDetailH($cvFileName); ?></strong>
              <p><?php echo $cvUrl !== '' ? 'Đã tải lên · '.$cvUploadedAt : 'Ứng viên chưa tải CV đính kèm'; ?></p>

              <?php if($cvUrl !== ''){ ?>
                <div class="cd-file-actions">
                  <a href="<?php echo candidateDetailH($cvUrl); ?>" class="cd-download-btn" download>
                    <i class="ti ti-download"></i> Tải CV về
                  </a>

                  <a href="<?php echo candidateDetailH($cvUrl); ?>" class="cd-preview-btn" target="_blank" rel="noopener">
                    <i class="ti ti-eye"></i> Xem trước
                  </a>
                </div>
              <?php } ?>
            </div>
          </div>
        </section>

        <!-- <section class="cd-card">
          <button class="cd-btn cd-btn-blue" type="button" style="width:100%;justify-content:center"><i class="ti ti-send"></i> Mời ứng viên ứng tuyển</button>
        </section> -->

        <section class="cd-card">
          <h2 class="cd-title"><i class="ti ti-users-star"></i> Ứng viên tiêu biểu</h2>

          <div class="cd-featured-candidates">
            <?php if(!empty($relatedCandidates)){ ?>
              <?php foreach(array_slice($relatedCandidates, 0, 4) as $relatedCandidate){ ?>
                <?php
                $relatedName = candidateDetailText($relatedCandidate->full_name ?? '', 'Ứng viên');
                $relatedAvatar = candidateDetailAvatarUrl($relatedCandidate->avatar_url ?? '');
                $relatedPosition = candidateDetailText($relatedCandidate->desired_position ?? ($relatedCandidate->job_category_name ?? ''), 'Ứng viên đang tìm việc');
                $relatedMetaOne = trim((string)($relatedCandidate->job_category_name ?? '')) !== '' ? trim((string)$relatedCandidate->job_category_name) : candidateDetailWorkTypeLabel($relatedCandidate->desired_work_type ?? '');
                $relatedMetaTwo = trim((string)($relatedCandidate->province_name ?? '')) !== '' ? trim((string)$relatedCandidate->province_name) : candidateDetailWorkTypeLabel($relatedCandidate->desired_work_type ?? '');
                ?>
                <a href="<?php echo candidateDetailH(candidateDetailUrl($relatedCandidate)); ?>" class="cd-candidate-mini">
                  <?php if($relatedAvatar !== ''){ ?>
                    <div class="cd-candidate-avatar"><img src="<?php echo candidateDetailH($relatedAvatar); ?>" alt="<?php echo candidateDetailH($relatedName); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:inherit" /></div>
                  <?php }else{ ?>
                    <div class="cd-candidate-avatar" style="background:<?php echo candidateDetailH(candidateDetailAccentColor($relatedCandidate->id ?? 0)); ?>"><?php echo candidateDetailH(candidateDetailInitials($relatedName)); ?></div>
                  <?php } ?>
                  <div class="cd-candidate-info">
                    <h4><?php echo candidateDetailH($relatedName); ?></h4>
                    <p><?php echo candidateDetailH($relatedPosition); ?></p>
                    <div class="cd-candidate-meta">
                      <span><?php echo candidateDetailH($relatedMetaOne); ?></span><span><?php echo candidateDetailH($relatedMetaTwo); ?></span>
                    </div>
                  </div>
                </a>
              <?php } ?>
            <?php }else{ ?>
              <div class="cd-content">
                <p>Chưa có ứng viên liên quan để hiển thị.</p>
              </div>
            <?php } ?>
          </div>
        </section>
      </aside>
    </div>

    <!-- <section class="news-slider">
      <div class="news-head">
        <h2 class="cd-title" style="margin-bottom:0"><i class="ti ti-news"></i> Tin tức nổi bật</h2>
        <div class="news-controls">
          <button class="news-btn" onclick="newsSlide(-1)"><i class="ti ti-chevron-left"></i></button>
          <button class="news-btn" onclick="newsSlide(1)"><i class="ti ti-chevron-right"></i></button>
        </div>
      </div>

      <div class="news-wrap">
        <div class="news-track" id="newsTrack">
          <a href="#" class="news-card"><div class="news-img"><i class="ti ti-school"></i></div><div class="news-body"><h3>Trường Cao đẳng Kon Tum đẩy mạnh kết nối doanh nghiệp</h3><p>Cập nhật chương trình hợp tác đào tạo và tuyển dụng sinh viên.</p></div></a>
          <a href="#" class="news-card"><div class="news-img"><i class="ti ti-file-cv"></i></div><div class="news-body"><h3>5 cách viết CV giúp sinh viên mới ra trường nổi bật</h3><p>Mẹo trình bày kỹ năng, kinh nghiệm và mục tiêu nghề nghiệp.</p></div></a>
          <a href="#" class="news-card"><div class="news-img"><i class="ti ti-briefcase"></i></div><div class="news-body"><h3>Những ngành nghề có nhu cầu tuyển dụng cao năm 2026</h3><p>Cơ hội việc làm cho sinh viên các khối ngành kỹ thuật, dịch vụ.</p></div></a>
          <a href="#" class="news-card"><div class="news-img"><i class="ti ti-users"></i></div><div class="news-body"><h3>Kỹ năng phỏng vấn dành cho ứng viên trẻ</h3><p>Chuẩn bị câu trả lời, tác phong và hồ sơ khi gặp nhà tuyển dụng.</p></div></a>
          <a href="#" class="news-card"><div class="news-img"><i class="ti ti-certificate"></i></div><div class="news-body"><h3>Chứng chỉ nào giúp hồ sơ ứng viên cạnh tranh hơn?</h3><p>Gợi ý chứng chỉ ngắn hạn phù hợp với từng nhóm ngành.</p></div></a>
        </div>
      </div>
      <div class="news-dots" id="newsDots"></div>
    </section> -->
  </div>
</main>

<script>
(function(){
  var current = 0;
  var timer = null;

  function visibleCount(){
    if(window.innerWidth <= 480) return 1;
    if(window.innerWidth <= 1024) return 2;
    return 3;
  }

  function maxSlide(){
    var cards = document.querySelectorAll('.news-card');
    return Math.max(cards.length - visibleCount(), 0);
  }

  function renderDots(){
    var dots = document.getElementById('newsDots');
    if(!dots) return;
    var html = '';
    for(var i=0;i<=maxSlide();i++){
      html += '<button class="news-dot '+(i===current?'active':'')+'" onclick="goNews('+i+')"></button>';
    }
    dots.innerHTML = html;
  }

  function update(){
    var track = document.getElementById('newsTrack');
    var card = document.querySelector('.news-card');
    if(!track || !card) return;

    if(current > maxSlide()) current = 0;
    if(current < 0) current = maxSlide();

    track.style.transform = 'translateX(-' + (current * (card.offsetWidth + 14)) + 'px)';
    renderDots();
  }

  function resetAuto(){
    clearInterval(timer);
    timer = setInterval(function(){
      current++;
      update();
    }, 5000);
  }

  window.newsSlide = function(dir){
    current += dir;
    update();
    resetAuto();
  };

  window.goNews = function(index){
    current = index;
    update();
    resetAuto();
  };

  window.addEventListener('resize', function(){
    current = 0;
    update();
    resetAuto();
  });

  document.addEventListener('DOMContentLoaded', function(){
    update();
    resetAuto();
  });
})();
</script>

<?php require "footer.php"; ?>
