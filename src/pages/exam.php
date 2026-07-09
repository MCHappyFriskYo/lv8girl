<div class="card">
  <div class="card-title"><i class="fas fa-pencil-alt"></i> 联考列表</div>
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

        let currentUser = null;
        try {
          const userRes = await fetch('?action=get_user');
          const userData = await userRes.json();
          if (userData.code === 0) currentUser = userData.data;
        } catch (e) {}

        let html = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.2rem;margin-top:1rem;">';
        for (const exam of exams) {
          const examId = exam.id || exam.exam_id;
          if (!examId) continue;

          const now = new Date();
          const start = exam.start_time ? new Date(exam.start_time) : null;
          const end = exam.end_time ? new Date(exam.end_time) : null;
          
          // 判断是否可进入（普通用户需要时间范围内，管理员/教师不受限）
          let canEnter = true;
          let timeMsg = '';
          if (!currentUser) {
            canEnter = false;
            timeMsg = '请登录';
          } else {
            const role = currentUser.role || '';
            if (role === 'ADMIN' || role === 'TEACHER') {
              canEnter = true;
            } else {
              if (start && now < start) {
                canEnter = false;
                timeMsg = '考试未开始';
              } else if (end && now > end) {
                canEnter = false;
                timeMsg = '考试已结束';
              } else if (exam.status === 'ended') {
                canEnter = false;
                timeMsg = '已收卷';
              }
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
              <div style="margin-top:0.8rem;font-size:0.9rem;color:#1e293b;">
                ${exam.question_count} 题 · 总分 ${exam.total_score}
              </div>
              <div style="margin-top:0.8rem;">
                ${!currentUser ? 
                  `<span style="color:#94a3b8;font-size:0.9rem;">请 <a href="?page=account" style="color:#2563eb;text-decoration:underline;">登录</a> 后查看</span>` :
                  (canEnter ? 
                    `<a href="exam_take.php?exam_id=${examId}" class="btn" style="display:inline-block;background:#0b3b4c;color:#fff;padding:0.3rem 1.2rem;border-radius:20px;text-decoration:none;font-size:0.9rem;">进入考试</a>` :
                    `<span style="color:#b91c1c;font-weight:500;">${timeMsg}</span>`
                  )
                }
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
