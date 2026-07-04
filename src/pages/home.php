<!-- 主页内容 -->
<div class="hero">
  <div class="hero-logo"><i class="fas fa-flask"></i></div>
  <h1><i class="fas fa-flask"></i>LunaticChO 联考平台</h1>
  <p>化学学科联考 · 答题卡收集 · 数据驱动教学</p>
</div>
<div class="card" style="border-top: 4px solid #d4a373;">
  <div class="card-title"><i class="fas fa-flag"></i>平台简介</div>
  <p style="color:#334155;">LunaticChO 为化学联考提供从试卷发布、答题卡扫描上传到成绩统计的全流程支持。考生通过邮箱注册，可随时上传答题卡，教师端统一收集，高效便捷。</p>
</div>
<div class="card" id="homeProgressCard">
  <div class="card-title">
    <i class="fas fa-calendar-check"></i>我的周常进度
    <span style="margin-left:auto; font-size:0.9rem; cursor:pointer; color:#2563eb;" onclick="renderHomeProgress();showToast('已刷新', 'success');">
      <i class="fas fa-sync-alt"></i> 刷新
    </span>
  </div>
  <div id="homeProgressContent"><p style="color:#94a3b8;text-align:center;padding:0.5rem 0;">加载中...</p></div>
</div>
<div class="card">
  <div class="card-title"><i class="fas fa-star"></i>核心功能</div>
  <div class="features-grid">
    <div class="feature-item"><i class="fas fa-envelope"></i><h4>邮箱注册</h4><p>快速注册，即时使用</p></div>
    <div class="feature-item"><i class="fas fa-upload"></i><h4>答题卡上传</h4><p>支持拖拽/点击，图片格式</p></div>
    <div class="feature-item"><i class="fas fa-chart-bar"></i><h4>数据管理</h4><p>自动归档，随时查阅</p></div>
  </div>
</div>