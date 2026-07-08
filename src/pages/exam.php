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

        let currentUser = null;
        try {
          const userRes = await fetch('?action=get_user');
          const userData = await userRes.json();
          if (userData.code === 0) currentUser = userData.data;
        } catch (e) {}

        let html = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.2rem;margin-top:1rem;">';
        for (const exam of exams) {
          let signupStatus = null;
          if (currentUser) {
            try {
              const sr = await fetch(`?action=get_signup_status&exam_id=${exam.id}`);
              const sd = await sr.json();
              if (sd.code === 0) signupStatus = sd.data;
            } catch (e) {}
          }
          const hasSigned = signupStatus && signupStatus.has_signed;
          const statusText = hasSigned ? (signupStatus.status === 'approved' ? '✅ 已通过' : (signupStatus.status === 'rejected' ? '❌ 已拒绝' : '⏳ 审核中')) : '';

          html += `
            <div style="background:#f8fafc;border-radius:10px;padding:1.2rem 1.5rem;border-left:4px solid #d4a373;">
              <div style="font-weight:600;font-size:1.1rem;color:#0b3b4c;">${exam.title}</div>
              <div style="font-size:0.85rem;color:#64748b;margin-top:0.3rem;">
                <span>👩‍🏫 ${exam.teacher}</span>
                <span style="margin-left:1rem;">📅 ${new Date(exam.published_at).toLocaleDateString()}</span>
              </div>
              <div style="margin-top:0.8rem;font-size:0.9rem;color:#1e293b;">
                ${exam.question_count} 题 · 总分 ${exam.total_score}
              </div>
              <div style="margin-top:0.8rem;">
                ${!currentUser ? 
                  `<span style="color:#94a3b8;font-size:0.9rem;">请 <a href="?page=account" style="color:#2563eb;text-decoration:underline;">登录</a> 后报名</span>` :
                  (hasSigned ? 
                    `<span style="color:#0b6b4c;font-weight:500;">${statusText}</span>` :
                    `<a href="exam_signup.php?exam_id=${exam.id}" class="btn" style="display:inline-block;background:#0b3b4c;color:#fff;padding:0.3rem 1.2rem;border-radius:20px;text-decoration:none;font-size:0.9rem;transition:background 0.15s;">立即报名</a>`
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
