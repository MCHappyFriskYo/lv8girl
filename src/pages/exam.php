<div class="card">
  <div class="card-title"><i class="fas fa-pencil-alt"></i>联考报名</div>
  <div id="examListContainer">
    <!-- 由 JavaScript 动态渲染 -->
  </div>
</div>

<script>
  (function() {
    const container = document.getElementById('examListContainer');
    if (!container) return;

    async function loadExams() {
      try {
        const res = await fetch('?action=get_exams&type=exam');
        const data = await res.json();
        if (data.code !== 0) {
          container.innerHTML = `<p style="color:#b91c1c;">加载失败：${data.message}</p>`;
          return;
        }
        const exams = data.data;
        if (!exams || exams.length === 0) {
          container.innerHTML = '<p style="color:#94a3b8;">暂无联考，请关注后续通知</p>';
          return;
        }

        // 获取当前用户角色
        let currentUser = null;
        try {
          const userRes = await fetch('?action=get_user');
          const userData = await userRes.json();
          if (userData.code === 0) currentUser = userData.data;
        } catch (e) {}

        const isAdmin = currentUser && ['ADMIN', 'TEACHER'].includes(currentUser.role);

        let html = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.2rem;margin-top:1rem;">';
        for (const exam of exams) {
          const now = new Date();
          const start = exam.start_time ? new Date(exam.start_time) : null;
          const end = exam.end_time ? new Date(exam.end_time) : null;
          let statusText = '';
          let canEnter = false;
          let link = '';

          if (isAdmin) {
            // 管理员可随时进入（但需要报名状态？暂时不强制报名，可加报名检查）
            canEnter = true;
            statusText = '👨‍🏫 管理员入口';
            link = `exam_take.php?exam_id=${exam.id}&admin=1`;
          } else {
            if (start && now < start) {
              statusText = '⏳ 尚未开始 (' + start.toLocaleString() + ')';
              canEnter = false;
            } else if (end && now > end) {
              statusText = '🔒 已结束';
              canEnter = false;
            } else {
              // 在答题时间内，检查是否已报名且审核通过？这里简化：只要有报名记录且状态为approved，或者不限制报名
              // 暂时先放行，报名逻辑后续可加
              canEnter = true;
              statusText = '📝 答题中';
              link = `exam_take.php?exam_id=${exam.id}`;
            }
          }

          html += `
            <div style="background:#f8fafc;border-radius:10px;padding:1.2rem 1.5rem;border-left:4px solid #d4a373;">
              <div style="font-weight:600;font-size:1.1rem;color:#0b3b4c;">${exam.title}</div>
              <div style="font-size:0.85rem;color:#64748b;margin-top:0.3rem;">
                <span>👩‍🏫 ${exam.teacher}</span>
                <span style="margin-left:1rem;">📅 ${new Date(exam.published_at).toLocaleDateString()}</span>
              </div>
              <div style="margin-top:0.5rem;font-size:0.85rem;color:#64748b;">
                ${exam.start_time ? '开始：' + new Date(exam.start_time).toLocaleString() : ''}
                ${exam.end_time ? ' 结束：' + new Date(exam.end_time).toLocaleString() : ''}
              </div>
              <div style="margin-top:0.8rem;">
                ${canEnter ? `<a href="${link}" class="btn" style="display:inline-block;background:#0b3b4c;color:#fff;padding:0.3rem 1.2rem;border-radius:20px;text-decoration:none;font-size:0.9rem;transition:background 0.15s;">进入考试</a>` : `<span style="color:#94a3b8;font-size:0.9rem;">${statusText}</span>`}
              </div>
            </div>
          `;
        }
        html += '</div>';
        container.innerHTML = html;
      } catch (e) {
        container.innerHTML = '<p style="color:#b91c1c;">加载失败，请刷新重试</p>';
        console.error(e);
      }
    }

    loadExams();
  })();
</script>
