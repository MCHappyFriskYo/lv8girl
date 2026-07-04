<?php
/**
 * LunaticChO 主入口
 * 根据 ?page= 参数加载不同页面
 * 所有 API 请求仍由本文件处理（action 参数）
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

// ========== 数据库配置 ==========
function gsk_config() {
    $host = 'db';
    $dbname = 'lv8girl';
    $db_user = 'lv8girl';
    $db_pass = 'yourpasswd'; // 请修改
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => 50000, 'message' => '数据库连接失败：' . $e->getMessage()]);
        exit;
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// ========== 处理 API 请求（与之前完全相同，省略以节省篇幅）==========
// 实际部署时请复制完整的 API 处理逻辑（从 if (isset($_REQUEST['action'])) 到结束）
// 由于篇幅，此处省略，但必须包含所有 action 处理。
// 您可以直接使用之前版本的 API 部分，或者在下文提供完整代码。

// ================================================================
// 没有 action 参数，输出 HTML 页面
// ================================================================
ob_end_clean();
header('Content-Type: text/html; charset=utf-8');

// 获取当前页面参数，默认为 home
$page = $_GET['page'] ?? 'home';
$allowed_pages = ['home', 'exam', 'museum', 'weekly', 'account'];
if (!in_array($page, $allowed_pages)) {
    $page = 'home';
}

// 定义常量供页面文件使用
define('CURRENT_PAGE', $page);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LunaticChO · 联考平台</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
  <style>
    /* ===== 全局样式（与之前一致） ===== */
    /* 此处省略样式，请复制之前的完整样式 */
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background: #f6f8fa;
      color: #1e293b;
      line-height: 1.5;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background-image: linear-gradient(rgba(11,59,76,0.02) 1px,transparent 1px),linear-gradient(90deg,rgba(11,59,76,0.02) 1px,transparent 1px);
      background-size: 40px 40px;
    }
    a { text-decoration: none; color: inherit; }
    .navbar {
      background: #0b3b4c;
      padding: 0 2.5rem;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 2px solid #d4a373;
      position: sticky;
      top: 0;
      z-index: 100;
    }
    .navbar .brand {
      font-size: 1.4rem;
      font-weight: 700;
      color: #f0e6d3;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .navbar .brand i { color: #d4a373; font-size: 1.6rem; }
    .nav-links {
      display: flex;
      list-style: none;
      gap: 2rem;
      align-items: center;
    }
    .nav-links li a {
      color: #cbd5e1;
      font-weight: 500;
      font-size: 0.95rem;
      padding: 0.3rem 0;
      border-bottom: 2px solid transparent;
      transition: border-color 0.2s, color 0.2s;
    }
    .nav-links li a:hover,
    .nav-links li a.active {
      color: #ffffff;
      border-bottom-color: #d4a373;
    }
    .hamburger {
      display: none;
      flex-direction: column;
      gap: 4px;
      cursor: pointer;
      background: none;
      border: none;
      padding: 4px;
    }
    .hamburger span { display: block; width: 26px; height: 2px; background: #cbd5e1; border-radius: 2px; }
    .container {
      max-width: 1140px;
      margin: 0 auto;
      padding: 2rem 1.5rem;
      flex: 1;
      width: 100%;
    }
    .card {
      background: #ffffff;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.04);
      padding: 1.8rem 2rem;
      margin-bottom: 2rem;
      border: 1px solid #e9edf2;
      transition: box-shadow 0.2s;
    }
    .card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.06); }
    .card-title {
      font-size: 1.3rem;
      font-weight: 600;
      margin-bottom: 1rem;
      color: #0b3b4c;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .card-title i { color: #d4a373; width: 1.6rem; text-align: center; }
    .hero {
      background: linear-gradient(145deg, #ffffff, #f9fafb);
      border-radius: 12px;
      padding: 2.5rem 2.5rem 2rem;
      margin-bottom: 2rem;
      border: 1px solid #e9edf2;
      text-align: center;
    }
    .hero-logo {
      display: inline-block;
      width: 100px;
      height: 100px;
      border-radius: 50%;
      background: #f0f4f8;
      border: 3px solid #d4a373;
      box-shadow: 0 4px 12px rgba(0,0,0,0.06);
      margin-bottom: 1rem;
      line-height: 100px;
      text-align: center;
      font-size: 2.8rem;
      color: #0b3b4c;
      transition: transform 0.2s;
    }
    .hero-logo:hover { transform: scale(1.02); }
    .hero-logo i { color: #0b3b4c; }
    .hero h1 {
      font-size: 2.6rem;
      font-weight: 700;
      color: #0b3b4c;
      letter-spacing: -0.5px;
    }
    .hero h1 i { color: #d4a373; margin-right: 8px; }
    .hero p {
      font-size: 1.15rem;
      color: #475569;
      max-width: 600px;
      margin: 0.4rem auto 0;
    }
    .features-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1.5rem;
      margin-top: 1.8rem;
    }
    .feature-item {
      background: #f8fafc;
      padding: 1.5rem 0.8rem;
      border-radius: 8px;
      text-align: center;
      border: 1px solid #e9edf2;
      transition: transform 0.15s;
    }
    .feature-item:hover { transform: translateY(-2px); }
    .feature-item i { font-size: 2rem; color: #0b3b4c; margin-bottom: 0.5rem; display: block; }
    .feature-item h4 { font-weight: 600; color: #0b3b4c; }
    .feature-item p { color: #64748b; font-size: 0.9rem; margin-top: 0.2rem; }
    .exam-empty {
      text-align: center;
      padding: 3rem 0;
      color: #94a3b8;
    }
    .exam-empty i { font-size: 4rem; color: #d4a373; margin-bottom: 1rem; display: block; }
    .exam-empty h3 { font-size: 1.5rem; color: #0b3b4c; margin-bottom: 0.5rem; }

    /* ===== 主页进度卡片 ===== */
    .progress-card {
      background: #f8fafc;
      border-radius: 10px;
      padding: 1.2rem 1.5rem;
      margin-bottom: 0.8rem;
      border-left: 4px solid #d4a373;
      cursor: pointer;
      transition: background 0.15s, transform 0.15s;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 0.5rem;
    }
    .progress-card:hover { background: #f1f5f9; transform: translateX(4px); }
    .progress-card .info { flex: 1; }
    .progress-card .title { font-weight: 600; color: #0b3b4c; font-size: 1rem; }
    .progress-card .meta { font-size: 0.85rem; color: #64748b; }
    .status-badge {
      display: inline-block;
      padding: 0.15rem 0.8rem;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
      white-space: nowrap;
    }
    .status-not-started { background: #e2e8f0; color: #475569; }
    .status-pending { background: #fef3c7; color: #b45309; }
    .status-graded { background: #d1fae5; color: #0b6b4c; }
    .no-exams-msg {
      color: #94a3b8;
      text-align: center;
      padding: 1.5rem 0;
    }
    .no-exams-msg i { font-size: 2.5rem; display: block; margin-bottom: 0.5rem; color: #d4a373; }

    /* ===== 博物志 PDF 阅读器 ===== */
    .pdf-container {
      max-width: 900px;
      margin: 0 auto;
      background: #ffffff;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.06);
      padding: 1rem;
      border: 1px solid #e9edf2;
    }
    .pdf-viewer {
      position: relative;
      width: 100%;
      min-height: 500px;
      background: #f8fafc;
      border-radius: 8px;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .pdf-viewer canvas {
      max-width: 100%;
      height: auto;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    .pdf-controls {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 1.5rem;
      margin-top: 1rem;
      padding: 0.6rem;
      background: #f8fafc;
      border-radius: 8px;
      flex-wrap: wrap;
    }
    .pdf-controls button {
      background: #0b3b4c;
      color: #fff;
      border: none;
      padding: 0.4rem 1.2rem;
      border-radius: 20px;
      cursor: pointer;
      transition: background 0.15s;
      font-size: 0.9rem;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .pdf-controls button:hover { background: #0a2f3d; }
    .pdf-controls button:disabled { opacity: 0.4; cursor: not-allowed; }
    .pdf-controls .page-info {
      font-weight: 600;
      color: #0b3b4c;
      min-width: 80px;
      text-align: center;
    }
    .pdf-loading {
      text-align: center;
      padding: 2rem;
      color: #94a3b8;
    }
    .pdf-loading i { font-size: 2.5rem; display: block; margin-bottom: 0.5rem; }

    /* ===== 周常页面 ===== */
    .exam-cards {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1.2rem;
      margin-top: 1rem;
    }
    .exam-card-item {
      background: #f8fafc;
      border-radius: 10px;
      padding: 1.2rem 1.5rem;
      border-left: 4px solid #d4a373;
      cursor: pointer;
      transition: background 0.15s, transform 0.15s;
      box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    }
    .exam-card-item:hover { background: #f1f5f9; transform: translateY(-2px); }
    .exam-card-item .title { font-size: 1.1rem; font-weight: 600; color: #0b3b4c; }
    .exam-card-item .meta {
      display: flex;
      justify-content: space-between;
      font-size: 0.85rem;
      color: #64748b;
      margin-top: 0.3rem;
    }
    .exam-card-item .badge-status {
      display: inline-block;
      padding: 0.1rem 0.6rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      background: #d1fae5;
      color: #0b6b4c;
    }

    .ranking-section {
      background: #f8fafc;
      border-radius: 10px;
      padding: 1.2rem 1.5rem;
      margin-bottom: 1.5rem;
      border: 1px solid #e9edf2;
    }
    .ranking-section h3 {
      color: #0b3b4c;
      margin-bottom: 0.8rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .ranking-list {
      display: flex;
      flex-direction: column;
      gap: 0.3rem;
    }
    .ranking-row {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 0.3rem 0.5rem;
      border-bottom: 1px solid #e9edf2;
    }
    .ranking-row .rank {
      font-weight: 700;
      width: 30px;
      text-align: center;
    }
    .ranking-row .rank.gold { color: #f59e0b; }
    .ranking-row .rank.silver { color: #94a3b8; }
    .ranking-row .rank.bronze { color: #d97706; }
    .ranking-row .name { flex: 1; }
    .ranking-row .score { font-weight: 700; color: #0b3b4c; }

    /* 答题模式 */
    .exam-container { max-width: 800px; margin: 0 auto; }
    .exam-header {
      text-align: center;
      padding-bottom: 1rem;
      border-bottom: 2px solid #e9edf2;
      margin-bottom: 1.5rem;
    }
    .exam-header h2 { color: #0b3b4c; font-size: 1.8rem; }
    .exam-header p { color: #64748b; }
    .question-item {
      background: #f8fafc;
      padding: 1.2rem 1.5rem;
      border-radius: 8px;
      margin-bottom: 1rem;
      border: 1px solid #e9edf2;
    }
    .question-item .q-header {
      display: flex;
      justify-content: space-between;
      font-weight: 600;
      color: #0b3b4c;
      margin-bottom: 0.5rem;
    }
    .question-item .q-type {
      font-weight: 400;
      font-size: 0.8rem;
      padding: 0.1rem 0.6rem;
      border-radius: 12px;
      background: #dbeafe;
      color: #1d4ed8;
    }
    .question-item .q-content { margin-bottom: 0.8rem; color: #1e293b; }
    .question-item .q-content img { max-width: 100%; height: auto; border-radius: 4px; margin: 0.5rem 0; }
    .question-item .q-options label {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.3rem 0;
      cursor: pointer;
    }
    .question-item .q-options input[type="radio"],
    .question-item .q-options input[type="checkbox"] {
      accent-color: #0b3b4c;
      width: 16px;
      height: 16px;
      flex-shrink: 0;
    }
    .question-item .q-options textarea {
      width: 100%;
      padding: 0.5rem;
      border: 1px solid #d1d9e6;
      border-radius: 6px;
      font-size: 0.95rem;
      min-height: 60px;
      font-family: inherit;
    }
    .question-item .q-options input[type="text"] {
      width: 100%;
      padding: 0.5rem;
      border: 1px solid #d1d9e6;
      border-radius: 6px;
      font-size: 0.95rem;
    }
    .btn-submit-exam {
      width: 100%;
      padding: 0.8rem;
      background: #0b3b4c;
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 1.1rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.15s;
    }
    .btn-submit-exam:hover { background: #0a2f3d; }
    .btn-submit-exam:disabled { opacity: 0.6; cursor: not-allowed; }

    /* 排行榜独立视图 */
    .ranking-container { max-width: 600px; margin: 0 auto; }
    .ranking-item {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 0.6rem 1rem;
      border-bottom: 1px solid #e9edf2;
    }
    .ranking-item .rank {
      font-weight: 700;
      font-size: 1.1rem;
      color: #0b3b4c;
      width: 40px;
      text-align: center;
    }
    .ranking-item .rank.gold { color: #f59e0b; }
    .ranking-item .rank.silver { color: #94a3b8; }
    .ranking-item .rank.bronze { color: #d97706; }
    .ranking-item .avatar-small {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: #dbeafe;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      flex-shrink: 0;
    }
    .ranking-item .avatar-small img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .ranking-item .name { flex: 1; font-weight: 500; }
    .ranking-item .score { font-weight: 700; color: #0b3b4c; }
    .my-score {
      background: #fef3c7;
      padding: 1rem;
      border-radius: 8px;
      text-align: center;
      margin-top: 1rem;
      border: 2px solid #f59e0b;
    }
    .my-score .big-score { font-size: 2rem; font-weight: 700; color: #0b3b4c; }

    /* 答题回顾 */
    .answer-review .user-answer {
      background: #f1f5f9;
      padding: 0.3rem 0.8rem;
      border-radius: 4px;
      display: inline-block;
      margin: 0.2rem 0;
    }
    .answer-review .score-badge {
      font-weight: 700;
      padding: 0.1rem 0.8rem;
      border-radius: 20px;
      font-size: 0.85rem;
    }
    .answer-review .score-badge.correct { background: #d1fae5; color: #0b6b4c; }
    .answer-review .score-badge.wrong { background: #fce4ec; color: #b91c1c; }
    .answer-review .score-badge.pending { background: #fef3c7; color: #b45309; }

    /* 账号样式 */
    .auth-tabs {
      display: flex;
      border-bottom: 2px solid #e9edf2;
      margin-bottom: 1.5rem;
    }
    .auth-tabs button {
      flex: 1;
      padding: 0.6rem 0;
      border: none;
      background: transparent;
      font-size: 1rem;
      font-weight: 600;
      color: #64748b;
      cursor: pointer;
      border-bottom: 2px solid transparent;
      transition: 0.15s;
    }
    .auth-tabs button.active { color: #0b3b4c; border-bottom-color: #d4a373; }
    .auth-tabs button:hover { color: #0b3b4c; }
    .auth-form {
      display: none;
      flex-direction: column;
      gap: 1rem;
      max-width: 400px;
      margin: 0 auto;
    }
    .auth-form.active { display: flex; }
    .auth-form label { font-weight: 500; font-size: 0.9rem; color: #334155; }
    .auth-form .input-group {
      display: flex;
      align-items: center;
      background: #f1f5f9;
      border-radius: 6px;
      padding: 0 0.8rem;
      border: 1px solid #e2e8f0;
      transition: border-color 0.15s;
    }
    .auth-form .input-group:focus-within { border-color: #0b3b4c; background: #ffffff; }
    .auth-form .input-group i { color: #94a3b8; font-size: 0.95rem; margin-right: 8px; }
    .auth-form .input-group input {
      width: 100%;
      padding: 0.6rem 0;
      border: none;
      background: transparent;
      font-size: 0.95rem;
      outline: none;
      color: #0f172a;
    }
    .auth-form .btn-primary {
      background: #0b3b4c;
      color: white;
      border: none;
      padding: 0.7rem 0;
      border-radius: 6px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.15s;
    }
    .auth-form .btn-primary:hover { background: #0a2f3d; }
    .auth-form .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
    .auth-form .form-error { color: #b91c1c; font-size: 0.85rem; background: #fef2f2; padding: 0.4rem 0.8rem; border-radius: 4px; display: none; }
    .auth-form .form-success { color: #0b6b4c; font-size: 0.85rem; background: #f0fdf4; padding: 0.4rem 0.8rem; border-radius: 4px; display: none; }

    .user-profile {
      display: none;
      text-align: center;
      padding: 1rem 0;
    }
    .user-profile.active { display: block; }
    .user-profile .avatar-wrap {
      position: relative;
      width: 100px;
      height: 100px;
      margin: 0 auto 0.8rem;
      cursor: pointer;
    }
    .user-profile .avatar-wrap img {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #d4a373;
      background: #f0f4f8;
    }
    .user-profile .avatar-wrap .avatar-placeholder {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      background: #dbeafe;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 3rem;
      color: #0b3b4c;
      border: 3px solid #d4a373;
    }
    .user-profile .avatar-wrap .upload-hint {
      position: absolute;
      bottom: 0;
      right: 0;
      background: #0b3b4c;
      color: #fff;
      border-radius: 50%;
      width: 28px;
      height: 28px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.8rem;
      border: 2px solid #fff;
    }
    .user-profile .user-email { font-size: 1.1rem; font-weight: 600; color: #0b3b4c; }
    .user-profile .user-qq { color: #64748b; font-size: 0.9rem; }
    .user-profile .user-role {
      display: inline-block;
      background: #dbeafe;
      color: #0b3b4c;
      padding: 0.1rem 0.8rem;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
      margin-top: 0.2rem;
    }
    .user-profile .btn-group {
      margin-top: 1rem;
      display: flex;
      gap: 0.8rem;
      justify-content: center;
      flex-wrap: wrap;
    }
    .user-profile .btn-logout {
      background: #e2e8f0;
      color: #1e293b;
      border: none;
      padding: 0.4rem 1.8rem;
      border-radius: 20px;
      font-weight: 500;
      cursor: pointer;
      font-size: 0.9rem;
      transition: background 0.15s;
    }
    .user-profile .btn-logout:hover { background: #cbd5e1; }
    .user-profile .btn-admin {
      background: #0b3b4c;
      color: #fff;
      border: none;
      padding: 0.4rem 1.8rem;
      border-radius: 20px;
      font-weight: 500;
      cursor: pointer;
      font-size: 0.9rem;
      transition: background 0.15s;
      text-decoration: none;
      display: inline-block;
    }
    .user-profile .btn-admin:hover { background: #0a2f3d; }
    #avatarInput { display: none; }

    .toast {
      position: fixed;
      bottom: 24px;
      right: 24px;
      padding: 0.8rem 1.6rem;
      border-radius: 8px;
      background: #0b3b4c;
      color: #fff;
      font-weight: 500;
      font-size: 0.9rem;
      box-shadow: 0 4px 16px rgba(0,0,0,0.12);
      opacity: 0;
      transform: translateY(16px);
      transition: 0.25s ease;
      z-index: 9999;
      max-width: 360px;
    }
    .toast.show { opacity: 1; transform: translateY(0); }
    .toast.success { background: #0b6b4c; }
    .toast.error { background: #b91c1c; }

    .footer {
      background: #0b3b4c;
      color: #94a3b8;
      text-align: center;
      padding: 1.2rem 1rem;
      font-size: 0.85rem;
      border-top: 2px solid #d4a373;
      margin-top: 1.5rem;
    }
    .footer span { color: #d4a373; }

    @media (max-width: 820px) {
      .navbar { padding: 0 1.5rem; }
      .features-grid { grid-template-columns: 1fr 1fr; }
      .hero-logo { width: 80px; height: 80px; line-height: 80px; font-size: 2.2rem; }
      .pdf-viewer { min-height: 350px; }
    }
    @media (max-width: 640px) {
      .hamburger { display: flex; }
      .nav-links {
        position: absolute;
        top: 64px;
        left: 0;
        right: 0;
        background: #0b3b4c;
        flex-direction: column;
        padding: 0.8rem 0;
        gap: 0;
        display: none;
        border-top: 1px solid #1e4c5e;
      }
      .nav-links.open { display: flex; }
      .nav-links li { width: 100%; text-align: center; }
      .nav-links li a { padding: 0.6rem 0; border-bottom: none; }
      .nav-links li a:hover { background: #1e4c5e; color: #fff; }
      .features-grid { grid-template-columns: 1fr; }
      .container { padding: 1rem 0.8rem; }
      .card { padding: 1.2rem; }
      .hero { padding: 1.8rem 1.2rem; }
      .hero h1 { font-size: 2rem; }
      .hero-logo { width: 72px; height: 72px; line-height: 72px; font-size: 2rem; }
      .pdf-viewer { min-height: 280px; }
      .exam-cards { grid-template-columns: 1fr; }
    }
    @media (max-width: 400px) {
      .pdf-viewer { min-height: 200px; }
    }
  </style>
</head>
<body>
  <!-- 导航 -->
  <nav class="navbar">
    <div class="brand"><i class="fas fa-flask"></i> LunaticChO</div>
    <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
    <ul class="nav-links" id="navLinks">
      <li><a href="?page=home" class="<?= $page === 'home' ? 'active' : '' ?>">主页</a></li>
      <li><a href="?page=exam" class="<?= $page === 'exam' ? 'active' : '' ?>">联考</a></li>
      <li><a href="?page=museum" class="<?= $page === 'museum' ? 'active' : '' ?>">博物志</a></li>
      <li><a href="?page=weekly" class="<?= $page === 'weekly' ? 'active' : '' ?>">周常</a></li>
      <li><a href="?page=account" class="<?= $page === 'account' ? 'active' : '' ?>">账号</a></li>
    </ul>
  </nav>

  <div class="container">
    <?php
    $page_file = __DIR__ . '/pages/' . $page . '.php';
    if (file_exists($page_file)) {
        include $page_file;
    } else {
        echo '<p style="color:#b91c1c;">页面不存在</p>';
    }
    ?>
  </div>

  <footer class="footer"><p>© 2026 <span>LunaticChO</span> · 化学联考平台</p></footer>
  <div class="toast" id="toast"></div>

  <script>
    (function() {
      'use strict';

      // 当前页面标识（由 PHP 传入）
      const CURRENT_PAGE = '<?= $page ?>';

      // ========== API 调用层 ==========
      const API = {
        baseURL: window.location.pathname,

        async _request(action, data = null, method = 'POST') {
            const url = this.baseURL + '?action=' + action;
            const options = {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: data ? JSON.stringify(data) : undefined
            };
            const res = await fetch(url, options);
            const json = await res.json();
            if (!res.ok) throw json;
            return json;
        },

        async _get(action, params = {}) {
            const url = this.baseURL + '?action=' + action + '&' + new URLSearchParams(params);
            const res = await fetch(url, { credentials: 'include' });
            const json = await res.json();
            if (!res.ok) throw json;
            return json;
        },

        async login(username, password) {
            return this._request('login', { username, password });
        },
        async register(username, email, password, qq) {
            return this._request('register', { username, email, password, qq });
        },
        async getCurrentUser() {
            return this._request('get_user', null, 'GET');
        },
        async logout() {
            return this._request('logout');
        },
        async updateAvatar(avatar) {
            return this._request('update_avatar', { avatar });
        },
        async getExams() {
            return this._get('get_exams');
        },
        async getUserExamStatus() {
            return this._get('get_user_exam_status');
        },
        async getExamQuestions(exam_id) {
            return this._get('get_exam_questions', { exam_id });
        },
        async getUserAnswers(exam_id) {
            return this._get('get_user_answers', { exam_id });
        },
        async submitAnswers(exam_id, answers) {
            return this._request('submit_answers', { exam_id, answers });
        },
        async getRanking(exam_id) {
            return this._get('get_ranking', { exam_id });
        }
      };

      // ========== 公用 UI 控制 ==========
      const $ = (s) => document.querySelector(s);
      const $$ = (s) => document.querySelectorAll(s);

      const hamburger = $('#hamburger');
      const navList = $('#navLinks');

      hamburger.addEventListener('click', () => navList.classList.toggle('open'));

      function showToast(msg, type = 'info') {
        const t = $('#toast');
        t.textContent = msg;
        t.className = 'toast show ' + type;
        clearTimeout(t._timer);
        t._timer = setTimeout(() => t.classList.remove('show'), 3000);
      }

      // ========== 全局状态 ==========
      let currentUser = null;
      let currentExamId = null;
      let examQuestions = [];

      // ========== 头像上传 ==========
      function handleAvatarUpload(file) {
        if (!file) return;
        if (!file.type.startsWith('image/')) {
          showToast('请上传图片文件', 'error');
          return;
        }
        const maxSize = 2 * 1024 * 1024;
        if (file.size > maxSize) {
          showToast('图片不能超过2MB', 'error');
          return;
        }
        const reader = new FileReader();
        reader.onload = async function(e) {
          const base64 = e.target.result;
          try {
            const res = await API.updateAvatar(base64);
            if (res.code === 0) {
              showToast('头像更新成功', 'success');
              const img = $('#avatarImg');
              const placeholder = $('#avatarPlaceholder');
              img.src = base64;
              img.style.display = 'block';
              placeholder.style.display = 'none';
              if (currentUser) currentUser.avatar = base64;
            } else {
              showToast(res.message || '上传失败', 'error');
            }
          } catch (err) {
            showToast(err.message || '上传失败', 'error');
          }
        };
        reader.readAsDataURL(file);
      }

      // ========== 用户状态更新 ==========
      function updateUIForUser(user) {
        currentUser = user;
        const profile = $('#userProfile');
        const tabs = $('#authTabs');
        const forms = $$('.auth-form');
        const adminBtn = $('#adminBtn');
        const roleLabel = $('#profileRole');

        if (user) {
          profile.classList.add('active');
          tabs.style.display = 'none';
          forms.forEach(f => f.style.display = 'none');

          $('#profileEmail').textContent = user.username + ' (' + user.email + ')';
          $('#profileQQ').textContent = 'QQ: ' + (user.qq || '未设置');

          const roleMap = { 'ADMIN': '管理员', 'TEACHER': '教师', 'MARKER': '学生' };
          roleLabel.textContent = roleMap[user.role] || user.role;

          const img = $('#avatarImg');
          const placeholder = $('#avatarPlaceholder');
          if (user.avatar) {
            img.src = user.avatar;
            img.style.display = 'block';
            placeholder.style.display = 'none';
          } else {
            img.style.display = 'none';
            placeholder.style.display = 'flex';
          }

          if (user.role === 'ADMIN' || user.role === 'TEACHER') {
            adminBtn.style.display = 'inline-block';
          } else {
            adminBtn.style.display = 'none';
          }
        } else {
          profile.classList.remove('active');
          tabs.style.display = 'flex';
          forms.forEach(f => f.style.display = '');
          $$('.auth-tabs button').forEach(b => b.classList.remove('active'));
          document.querySelector('[data-tab="login"]').classList.add('active');
          $('#loginForm').classList.add('active');
          $('#registerForm').classList.remove('active');
          $('#loginError').style.display = 'none';
          $('#loginSuccess').style.display = 'none';
          $('#registerError').style.display = 'none';
          $('#registerSuccess').style.display = 'none';
          adminBtn.style.display = 'none';
        }

        // 根据当前页面渲染相应内容
        if (CURRENT_PAGE === 'home') {
          renderHomeProgress();
        } else if (CURRENT_PAGE === 'weekly') {
          renderWeekly();
        }
        // 账号页面不需要额外渲染
      }

      async function initUser() {
        try {
          const res = await API.getCurrentUser();
          if (res.code === 0) {
            updateUIForUser(res.data);
          } else {
            updateUIForUser(null);
          }
        } catch (e) {
          updateUIForUser(null);
        }
      }

      // 认证切换
      const authTabs = $$('.auth-tabs button');
      if (authTabs.length) {
        authTabs.forEach(tab => {
          tab.addEventListener('click', function() {
            authTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const target = this.dataset.tab;
            $$('.auth-form').forEach(f => f.classList.remove('active'));
            if (target === 'login') {
              $('#loginForm').classList.add('active');
              $('#loginError').style.display = 'none';
              $('#loginSuccess').style.display = 'none';
            } else {
              $('#registerForm').classList.add('active');
              $('#registerError').style.display = 'none';
              $('#registerSuccess').style.display = 'none';
            }
          });
        });
      }

      // 登录
      const loginForm = $('#loginForm');
      if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
          e.preventDefault();
          const username = $('#loginUsername').value.trim();
          const password = $('#loginPassword').value.trim();
          const err = $('#loginError');
          const suc = $('#loginSuccess');
          err.style.display = 'none';
          suc.style.display = 'none';
          if (!username || !password) { err.textContent = '请填写完整'; err.style.display = 'block'; return; }
          try {
            const res = await API.login(username, password);
            if (res.code === 0) {
              suc.textContent = '✅ 登录成功';
              suc.style.display = 'block';
              showToast('欢迎回来', 'success');
              $('#loginUsername').value = '';
              $('#loginPassword').value = '';
              await initUser();
            } else {
              err.textContent = res.message || '登录失败';
              err.style.display = 'block';
            }
          } catch (ex) {
            err.textContent = ex.message || '网络错误';
            err.style.display = 'block';
          }
        });
      }

      // 注册
      const registerForm = $('#registerForm');
      if (registerForm) {
        registerForm.addEventListener('submit', async function(e) {
          e.preventDefault();
          const username = $('#regUsername').value.trim();
          const email = $('#regEmail').value.trim();
          const password = $('#regPassword').value.trim();
          const qq = $('#regQQ').value.trim();
          const err = $('#registerError');
          const suc = $('#registerSuccess');
          err.style.display = 'none';
          suc.style.display = 'none';
          if (!username || !email || !password) {
            err.textContent = '用户名、邮箱和密码为必填项';
            err.style.display = 'block';
            return;
          }
          if (password.length < 6) {
            err.textContent = '密码至少6位';
            err.style.display = 'block';
            return;
          }
          try {
            const res = await API.register(username, email, password, qq);
            if (res.code === 0) {
              suc.textContent = '🎉 ' + res.message;
              suc.style.display = 'block';
              showToast('注册成功，请登录', 'success');
              $('#regUsername').value = '';
              $('#regEmail').value = '';
              $('#regPassword').value = '';
              $('#regQQ').value = '';
              document.querySelector('[data-tab="login"]').click();
            } else {
              err.textContent = res.message || '注册失败';
              err.style.display = 'block';
            }
          } catch (ex) {
            err.textContent = ex.message || '网络错误';
            err.style.display = 'block';
          }
        });
      }

      // 退出
      const logoutBtn = $('#logoutBtn');
      if (logoutBtn) {
        logoutBtn.addEventListener('click', async function() {
          try {
            await API.logout();
            await initUser();
            showToast('已退出', 'info');
            window.location.href = '?page=home';
          } catch (e) {
            showToast('退出失败', 'error');
          }
        });
      }

      // 头像上传点击
      const avatarWrap = $('#avatarWrap');
      const avatarInput = $('#avatarInput');
      if (avatarWrap) {
        avatarWrap.addEventListener('click', function() {
          if (!currentUser) {
            showToast('请先登录', 'error');
            return;
          }
          avatarInput.click();
        });
      }
      if (avatarInput) {
        avatarInput.addEventListener('change', function() {
          if (this.files.length > 0) {
            handleAvatarUpload(this.files[0]);
          }
          this.value = '';
        });
      }

      // ========== 主页进度 ==========
      async function renderHomeProgress() {
        const container = $('#homeProgressContent');
        if (!container) return;
        if (!currentUser) {
          container.innerHTML = `
            <div class="no-exams-msg">
              <i class="fas fa-sign-in-alt"></i>
              <p>请 <a href="?page=account" style="color:#2563eb;text-decoration:underline;">登录</a> 查看您的周常进度</p>
            </div>
          `;
          return;
        }
        try {
          const res = await API.getUserExamStatus();
          if (res.code === 0 && res.data && res.data.length > 0) {
            let html = '';
            res.data.forEach(exam => {
              let statusText, statusClass;
              if (!exam.has_answered) {
                statusText = '未作答';
                statusClass = 'status-not-started';
              } else if (exam.graded) {
                statusText = '✅ 已批改 ' + exam.score + ' 分';
                statusClass = 'status-graded';
              } else {
                statusText = '⏳ 待批改 (' + exam.answered_count + '/' + exam.question_count + ')';
                statusClass = 'status-pending';
              }
              html += `
                <div class="progress-card" onclick="window.LunaticChO.handleExamClick(${exam.id})">
                  <div class="info">
                    <div class="title">📝 ${exam.title}</div>
                    <div class="meta">${exam.question_count} 题 · 总分 ${exam.total_score}</div>
                  </div>
                  <span class="status-badge ${statusClass}">${statusText}</span>
                </div>
              `;
            });
            container.innerHTML = html;
          } else if (res.code !== 0) {
            container.innerHTML = `<p style="color:#b91c1c;text-align:center;">❌ ${res.message || '加载失败'}</p>`;
          } else {
            container.innerHTML = `<div class="no-exams-msg"><i class="fas fa-calendar-plus"></i><p>暂无已发布的考试</p></div>`;
          }
        } catch (e) {
          container.innerHTML = `<p style="color:#b91c1c;text-align:center;">❌ 加载失败，请稍后重试</p>`;
          console.error('主页进度加载错误:', e);
        }
      }

      // ========== 周常页面 ==========
      async function renderWeekly() {
        const container = $('#weeklyContainer');
        if (!container) return;
        try {
          const examsRes = await API.getExams();
          if (examsRes.code !== 0 || !examsRes.data || examsRes.data.length === 0) {
            container.innerHTML = '<p style="color:#94a3b8; text-align:center; padding:1rem 0;">暂无已发布的考试</p>';
            return;
          }
          const exams = examsRes.data;
          const latestExam = exams[0];

          let rankingHtml = '';
          try {
            const rankRes = await API.getRanking(latestExam.id);
            if (rankRes.code === 0 && rankRes.data.ranking && rankRes.data.ranking.length > 0) {
              const ranking = rankRes.data.ranking;
              let rows = '';
              ranking.forEach((item, idx) => {
                const rankClass = idx === 0 ? 'gold' : idx === 1 ? 'silver' : idx === 2 ? 'bronze' : '';
                const medal = idx === 0 ? '🥇' : idx === 1 ? '🥈' : idx === 2 ? '🥉' : `#${idx + 1}`;
                rows += `
                  <div class="ranking-row">
                    <div class="rank ${rankClass}">${medal}</div>
                    <div class="name">${item.username}</div>
                    <div class="score">${item.total_score} 分</div>
                  </div>
                `;
              });
              rankingHtml = `
                <div class="ranking-section">
                  <h3><i class="fas fa-trophy"></i> 最新考试「${latestExam.title}」排行榜</h3>
                  <div class="ranking-list">${rows}</div>
                </div>
              `;
            } else {
              rankingHtml = `
                <div class="ranking-section">
                  <h3><i class="fas fa-info-circle"></i> 最新考试「${latestExam.title}」</h3>
                  <p style="color:#94a3b8;">暂无已批改的成绩</p>
                </div>
              `;
            }
          } catch (e) {
            rankingHtml = `<div class="ranking-section"><p style="color:#94a3b8;">排行榜加载失败</p></div>`;
          }

          let cardsHtml = '<div class="exam-cards">';
          exams.forEach(exam => {
            cardsHtml += `
              <div class="exam-card-item" onclick="window.LunaticChO.handleExamClick(${exam.id})">
                <div class="title">📝 ${exam.title}</div>
                <div class="meta">
                  <span>👩‍🏫 ${exam.teacher}</span>
                  <span>📅 ${new Date(exam.published_at).toLocaleDateString()}</span>
                </div>
                <div style="margin-top:0.5rem; font-size:0.85rem; color:#64748b;">
                  ${exam.question_count} 题 · 总分 ${exam.total_score}
                  <span class="badge-status">已发布</span>
                </div>
              </div>
            `;
          });
          cardsHtml += '</div>';

          container.innerHTML = rankingHtml + cardsHtml;

        } catch (e) {
          container.innerHTML = '<p style="color:#b91c1c; text-align:center; padding:1rem 0;">❌ 加载失败，请稍后重试。</p>';
          console.error('周常加载错误:', e);
        }
      }

      // ========== 核心交互（考试） ==========
      window.LunaticChO = {
        handleExamClick: async function(examId) {
          if (!currentUser) {
            showToast('请先登录', 'error');
            window.location.href = '?page=account';
            return;
          }
          try {
            const statusRes = await API.getUserExamStatus();
            if (statusRes.code === 0 && statusRes.data) {
              const examStatus = statusRes.data.find(e => e.id === examId);
              if (examStatus && examStatus.has_answered) {
                this.viewAnswer(examId);
                return;
              }
            }
          } catch (e) {}
          this.startExam(examId);
        },

        startExam: async function(examId) {
          if (!currentUser) {
            showToast('请先登录后再答题', 'error');
            window.location.href = '?page=account';
            return;
          }
          currentExamId = examId;
          try {
            const res = await API.getExamQuestions(examId);
            if (res.code === 0) {
              examQuestions = res.data.questions || [];
              const exam = res.data.exam || { title: '考试', description: '' };
              this.renderExam(exam, examQuestions);
            } else {
              showToast(res.message || '加载题目失败', 'error');
            }
          } catch (e) {
            showToast('加载题目失败: ' + (e.message || ''), 'error');
          }
        },

        renderExam: function(exam, questions) {
          const container = $('#weeklyContainer');
          if (!container) return;
          if (!questions || questions.length === 0) {
            container.innerHTML = '<p style="color:#94a3b8; text-align:center; padding:1rem 0;">该考试暂无题目。</p>';
            return;
          }
          let html = `
            <div class="exam-container">
              <div class="exam-header">
                <h2>${exam.title || '考试'}</h2>
                <p>${exam.description || ''}</p>
                <p style="font-size:0.85rem; color:#94a3b8;">共 ${questions.length} 题，总分 ${questions.reduce((s, q) => s + parseInt(q.score || 0), 0)} 分</p>
                <button onclick="window.LunaticChO.backToExams()" style="background:none; border:none; color:#2563eb; cursor:pointer; font-size:0.9rem;">← 返回考试列表</button>
              </div>
              <form id="examForm">
          `;
          questions.forEach((q, idx) => {
            const qNum = idx + 1;
            const typeMap = { 'single': '单选题', 'multiple': '多选题', 'fill': '填空题', 'essay': '解答题' };
            html += `
              <div class="question-item">
                <div class="q-header">
                  <span>第 ${qNum} 题 <span class="q-type">${typeMap[q.type] || q.type}</span></span>
                  <span>${q.score || 0} 分</span>
                </div>
                <div class="q-content">${q.content || ''}</div>
                <div class="q-options">
            `;
            if (q.type === 'single') {
              const opts = q.options ? JSON.parse(q.options) : [];
              opts.forEach((opt, oi) => {
                const letter = String.fromCharCode(65 + oi);
                html += `<label><input type="radio" name="q_${q.id}" value="${letter}"> ${opt}</label>`;
              });
            } else if (q.type === 'multiple') {
              const opts = q.options ? JSON.parse(q.options) : [];
              opts.forEach((opt, oi) => {
                const letter = String.fromCharCode(65 + oi);
                html += `<label><input type="checkbox" name="q_${q.id}" value="${letter}"> ${opt}</label>`;
              });
            } else if (q.type === 'fill') {
              html += `<input type="text" name="q_${q.id}" placeholder="请输入答案" style="width:100%; padding:0.5rem; border:1px solid #d1d9e6; border-radius:6px;">`;
            } else { // essay
              html += `<textarea name="q_${q.id}" placeholder="请输入你的解答" rows="4"></textarea>`;
            }
            html += `</div></div>`;
          });
          html += `
                <button type="submit" class="btn-submit-exam">提交答案</button>
              </form>
              <div id="examResult" style="margin-top:1rem;"></div>
            </div>
          `;
          container.innerHTML = html;

          document.getElementById('examForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const answers = {};
            for (let [key, value] of formData.entries()) {
              const qid = parseInt(key.replace('q_', ''));
              if (value && value.trim()) {
                if (answers[qid]) {
                  answers[qid] += ',' + value.trim();
                } else {
                  answers[qid] = value.trim();
                }
              }
            }
            if (Object.keys(answers).length === 0) {
              showToast('请至少回答一道题', 'error');
              return;
            }
            try {
              const res = await API.submitAnswers(currentExamId, answers);
              if (res.code === 0) {
                document.getElementById('examResult').innerHTML = `
                  <div style="background:#d1fae5; padding:1rem; border-radius:8px; text-align:center; color:#0b6b4c;">
                    <i class="fas fa-check-circle" style="font-size:2rem; display:block; margin-bottom:0.5rem;"></i>
                    ${res.message}
                    <br><br>
                    <button onclick="window.LunaticChO.viewAnswer(${currentExamId})" class="btn" style="background:#0b3b4c; color:#fff; border:none; padding:0.5rem 1.5rem; border-radius:20px; cursor:pointer;">
                      查看我的答卷
                    </button>
                    <button onclick="window.LunaticChO.viewRanking(${currentExamId})" class="btn" style="background:#0b3b4c; color:#fff; border:none; padding:0.5rem 1.5rem; border-radius:20px; cursor:pointer;">
                      查看排行榜
                    </button>
                  </div>
                `;
                document.querySelector('.btn-submit-exam').disabled = true;
                if (CURRENT_PAGE === 'home') renderHomeProgress();
              } else {
                showToast(res.message || '提交失败', 'error');
              }
            } catch (e) {
              showToast(e.message || '提交失败', 'error');
            }
          });
        },

        viewAnswer: async function(examId) {
          const container = $('#weeklyContainer');
          if (!container) return;
          try {
            const res = await API.getUserAnswers(examId);
            if (res.code === 0) {
              const data = res.data;
              const exam = data.exam || { title: '考试' };
              const questions = data.questions || [];
              const result = data.result || null;
              const totalScore = result ? result.total_score : null;
              const graded = result && result.status === 'graded';

              let html = `
                <div class="exam-container answer-review">
                  <div class="exam-header">
                    <h2>📖 ${exam.title} - 答题回顾</h2>
                    <p style="font-size:0.9rem; color:#64748b;">
                      ${graded ? '✅ 已批改，总分：' + totalScore + ' 分' : '⏳ 待批改'}
                    </p>
                    <button onclick="window.LunaticChO.backToExams()" style="background:none; border:none; color:#2563eb; cursor:pointer; font-size:0.9rem;">← 返回考试列表</button>
                    <button onclick="window.LunaticChO.viewRanking(${examId})" style="background:#0b3b4c; color:#fff; border:none; padding:0.3rem 1.2rem; border-radius:20px; cursor:pointer; font-size:0.9rem; margin-left:0.5rem;">
                      查看排行榜
                    </button>
                  </div>
              `;

              if (questions.length === 0) {
                html += `<p style="color:#94a3b8; text-align:center; padding:1rem 0;">暂无题目数据</p>`;
              } else {
                questions.forEach((q, idx) => {
                  const qNum = idx + 1;
                  const typeMap = { 'single': '单选题', 'multiple': '多选题', 'fill': '填空题', 'essay': '解答题' };
                  const userAns = q.user_answer || '未作答';
                  const score = q.score;
                  const maxScore = q.max_score;
                  let scoreBadge = '';
                  if (graded && score !== null) {
                    const isCorrect = score == maxScore;
                    scoreBadge = `<span class="score-badge ${isCorrect ? 'correct' : 'wrong'}">${score} / ${maxScore} 分</span>`;
                  } else if (graded && score === null) {
                    scoreBadge = `<span class="score-badge pending">待批改</span>`;
                  } else {
                    scoreBadge = `<span class="score-badge pending">待批改</span>`;
                  }

                  let optionsDisplay = '';
                  if (q.type === 'single' || q.type === 'multiple') {
                    const opts = q.options ? JSON.parse(q.options) : [];
                    optionsDisplay = opts.map((opt, oi) => {
                      const letter = String.fromCharCode(65 + oi);
                      const isSelected = userAns.split(',').map(s => s.trim()).includes(letter);
                      return `<div style="display:flex;align-items:center;gap:0.5rem;padding:0.2rem 0; ${isSelected ? 'background:#dbeafe;border-radius:4px;padding-left:0.5rem;' : ''}">
                        <span>${letter}. ${opt}</span>
                        ${isSelected ? '<span style="font-size:0.8rem;color:#2563eb;font-weight:600;"> ← 你的答案</span>' : ''}
                      </div>`;
                    }).join('');
                  } else if (q.type === 'fill' || q.type === 'essay') {
                    optionsDisplay = `<div><strong>你的答案：</strong><span class="user-answer">${userAns}</span></div>`;
                  }

                  html += `
                    <div class="question-item">
                      <div class="q-header">
                        <span>第 ${qNum} 题 <span class="q-type">${typeMap[q.type] || q.type}</span></span>
                        <span>${scoreBadge}</span>
                      </div>
                      <div class="q-content">${q.content || ''}</div>
                      <div class="q-options">${optionsDisplay}</div>
                    </div>
                  `;
                });
              }

              html += `</div>`;
              container.innerHTML = html;
            } else {
              showToast(res.message || '加载答卷失败', 'error');
            }
          } catch (e) {
            showToast('加载答卷失败: ' + (e.message || ''), 'error');
          }
        },

        viewRanking: async function(examId) {
          const container = $('#weeklyContainer');
          if (!container) return;
          try {
            const res = await API.getRanking(examId);
            if (res.code === 0) {
              const data = res.data;
              let html = `
                <div class="ranking-container">
                  <div class="exam-header">
                    <h2>🏆 ${data.exam ? data.exam.title : '考试'} - 排行榜</h2>
                    <button onclick="window.LunaticChO.backToExams()" style="background:none; border:none; color:#2563eb; cursor:pointer; font-size:0.9rem;">← 返回考试列表</button>
                    <button onclick="window.LunaticChO.viewAnswer(${examId})" style="background:#0b3b4c; color:#fff; border:none; padding:0.3rem 1.2rem; border-radius:20px; cursor:pointer; font-size:0.9rem; margin-left:0.5rem;">
                      查看我的答卷
                    </button>
                  </div>
              `;
              if (data.myScore) {
                html += `
                  <div class="my-score">
                    你的得分：<span class="big-score">${data.myScore.total_score}</span> 分
                    ${data.myScore.status === 'graded' ? '✅ 已批改' : '⏳ 批改中...'}
                  </div>
                `;
              }
              if (data.ranking && data.ranking.length > 0) {
                html += `<div style="margin-top:1rem;">`;
                data.ranking.forEach((item, idx) => {
                  const rankClass = idx === 0 ? 'gold' : idx === 1 ? 'silver' : idx === 2 ? 'bronze' : '';
                  const medal = idx === 0 ? '🥇' : idx === 1 ? '🥈' : idx === 2 ? '🥉' : `#${idx + 1}`;
                  const avatarHtml = item.avatar ? `<img src="${item.avatar}" alt="">` : `<i class="fas fa-user"></i>`;
                  html += `
                    <div class="ranking-item">
                      <div class="rank ${rankClass}">${medal}</div>
                      <div class="avatar-small">${avatarHtml}</div>
                      <div class="name">${item.username}</div>
                      <div class="score">${item.total_score} 分</div>
                    </div>
                  `;
                });
                html += `</div>`;
              } else {
                html += `<p style="color:#94a3b8; text-align:center; padding:1rem 0;">暂无已完成批改的学生</p>`;
              }
              html += `</div>`;
              container.innerHTML = html;
            }
          } catch (e) {
            showToast('加载排行榜失败', 'error');
          }
        },

        backToExams: function() {
          window.location.href = '?page=weekly';
        }
      };

      // ========== 页面加载初始化 ==========
      // 先获取用户状态，完成后自动渲染对应页面（在 updateUIForUser 中已处理）
      initUser();

      // 菜单关闭
      document.addEventListener('click', (e) => {
        if (!e.target.closest('.navbar')) navList.classList.remove('open');
      });

      console.log('LunaticChO 启动，当前页面：', CURRENT_PAGE);
    })();
  </script>

  <!-- 页面特有的脚本（如博物志 PDF） -->
  <?php if ($page === 'museum'): ?>
    <script>
      (function() {
        'use strict';
        const PDF_URL = 'yan_shi_bo_wu_zhi.pdf';
        let pdfDoc = null,
            pageNum = 1,
            pageRendering = false,
            pageNumPending = null,
            scale = 2.0;
        const canvas = document.createElement('canvas');
        const viewer = document.getElementById('pdfViewer');
        if (!viewer) return;
        const ctx = canvas.getContext('2d');
        viewer.innerHTML = '';
        viewer.appendChild(canvas);

        function renderPage(num) {
          pageRendering = true;
          pdfDoc.getPage(num).then(function(page) {
            const viewport = page.getViewport({ scale: scale });
            canvas.height = viewport.height;
            canvas.width = viewport.width;
            canvas.style.width = '100%';
            canvas.style.height = 'auto';
            const renderContext = {
              canvasContext: ctx,
              viewport: viewport
            };
            const renderTask = page.render(renderContext);
            renderTask.promise.then(function() {
              pageRendering = false;
              if (pageNumPending !== null) {
                renderPage(pageNumPending);
                pageNumPending = null;
              }
            });
          });
          document.getElementById('pdfPageInfo').textContent = num + ' / ' + pdfDoc.numPages;
          document.getElementById('pdfPrev').disabled = (num <= 1);
          document.getElementById('pdfNext').disabled = (num >= pdfDoc.numPages);
        }

        function queueRenderPage(num) {
          if (pageRendering) {
            pageNumPending = num;
          } else {
            renderPage(num);
          }
        }

        function loadPDF() {
          pdfjsLib.getDocument(PDF_URL).promise.then(function(pdf) {
            pdfDoc = pdf;
            pageNum = 1;
            renderPage(pageNum);
          }).catch(function(err) {
            viewer.innerHTML = '<div style="text-align:center;padding:2rem;color:#b91c1c;"><i class="fas fa-exclamation-triangle" style="font-size:2.5rem;display:block;margin-bottom:0.5rem;"></i><p>无法加载 PDF 文件，请确保文件存在且路径正确。</p><p style="font-size:0.85rem;color:#94a3b8;">' + err.message + '</p></div>';
            console.error('PDF加载错误:', err);
          });
        }

        document.getElementById('pdfPrev').addEventListener('click', function() {
          if (pdfDoc && pageNum > 1) {
            pageNum--;
            queueRenderPage(pageNum);
          }
        });
        document.getElementById('pdfNext').addEventListener('click', function() {
          if (pdfDoc && pageNum < pdfDoc.numPages) {
            pageNum++;
            queueRenderPage(pageNum);
          }
        });

        loadPDF();
      })();
    </script>
  <?php endif; ?>
</body>
</html>
