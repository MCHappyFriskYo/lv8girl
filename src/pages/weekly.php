<!-- ===== 周常页面 ===== -->
<div class="card">
  <div class="card-title">
    <i class="fas fa-calendar-week"></i> 周常 · 化学挑战
    <span style="font-size:0.85rem; font-weight:400; color:#64748b; margin-left:0.5rem;">
      （每周小练习，在线答题）
    </span>
  </div>
  <div id="weeklyContainer">
    <p style="color:#94a3b8; text-align:center; padding:1.5rem 0;">
      <i class="fas fa-spinner fa-spin" style="margin-right:0.5rem;"></i> 加载中...
    </p>
  </div>
</div>

<!-- 
  注意：此页面依赖 index.php 中定义的：
  - API.getExams('weekly') 获取周常列表
  - window.LunaticChO.handleExamClick(examId) 处理点击进入答题
  - renderWeekly() 函数在 index.php 的 JavaScript 中已定义，会自动填充 #weeklyContainer
-->
