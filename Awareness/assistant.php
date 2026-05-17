<?php
$base_path = '../';
$page_title = 'المساعد الذكي - A.S.R';
$figma_page = 'awareness';
$assistant_entry = isset($_GET['entry']) ? trim((string)$_GET['entry']) : '';
$show_assistant_intake = ($assistant_entry === 'intake');
include __DIR__ . '/../includes/header.php';
?>

<style>
  body { margin: 0; padding: 0; }

  .assistant-shell {
    display: grid;
    gap: 24px;
    padding: 40px 0 60px;
  }

  .assistant-hero {
    background: linear-gradient(135deg, rgba(47,127,122,0.08), rgba(47,127,122,0.16));
    border: 1px solid var(--border-color);
    border-radius: 24px;
    padding: 28px;
    box-shadow: 0 10px 30px -5px rgba(0,0,0,0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
  }

  .assistant-hero h1 {
    margin: 0 0 10px;
    color: var(--primary);
    font-size: clamp(1.9rem, 3vw, 2.6rem);
  }

  .assistant-hero p {
    margin: 0;
    color: var(--text-main);
    line-height: 1.8;
    max-width: 760px;
  }

  .assistant-hero-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
  }

  .assistant-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 170px;
    padding: 12px 18px;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 700;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }

  .assistant-link:hover {
    transform: translateY(-2px);
  }

  .assistant-link.primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: #fff;
    box-shadow: 0 10px 24px rgba(47,127,122,0.24);
  }

  .assistant-link.secondary {
    background: var(--bg-card);
    color: var(--text-main);
    border: 1px solid var(--border-color);
  }

  .assistant-intake {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 32px;
    padding: 36px;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.06);
    margin-bottom: 40px;
    border: 1px solid var(--border-color);
  }

  .assistant-intake h2 {
    margin: 0 0 12px;
    color: var(--primary);
    font-size: 2rem;
    font-weight: 900;
    letter-spacing: -0.5px;
  }

  .assistant-intake > p {
    margin: 0 0 40px;
    color: var(--text-muted);
    line-height: 1.8;
    font-size: 1.1rem;
    max-width: 800px;
  }

  .assistant-intake-form {
    display: flex;
    flex-direction: column;
    gap: 40px;
  }

  .assistant-intake-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 28px;
  }

  .assistant-intake-item {
    padding: 30px 24px;
    border-radius: 28px;
    background: var(--bg-page);
    border: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  }

  .assistant-intake-item:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.06);
    border-color: var(--primary);
  }

  .question-kicker {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 18px;
    padding: 7px 12px;
    border-radius: 999px;
    background: rgba(47,127,122,0.08);
    border: 1px solid rgba(47,127,122,0.14);
    color: var(--primary);
    font-size: 0.82rem;
    font-weight: 800;
    letter-spacing: 0.01em;
  }

  .assistant-intake-item strong {
    display: block;
    margin-bottom: 24px;
    color: var(--text-main);
    font-size: 1.15rem;
    font-weight: 800;
    line-height: 1.5;
    min-height: 3.4em;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .assistant-intake-item strong.is-required::before {
    content: '*';
    color: #D32F2F;
    font-size: 1.2em;
    font-weight: 900;
    margin-left: 6px;
    line-height: 1;
  }

  /* Premium Segmented Control */
  .segmented-control {
    display: flex;
    background: var(--bg-card);
    padding: 5px;
    border-radius: 16px;
    border: 1px solid var(--border-color);
    width: 100%;
    position: relative;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
  }

  .segmented-control label {
    flex: 1;
    position: relative;
    z-index: 2;
    cursor: pointer;
  }

  .segmented-control input {
    position: absolute;
    opacity: 0;
    width: 0; height: 0;
  }

  .segmented-text {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px;
    border-radius: 12px;
    font-weight: 700;
    color: var(--text-muted);
    transition: all 0.3s;
    user-select: none;
    font-size: 0.95rem;
  }

  .segmented-control input:checked + .segmented-text {
    background: var(--primary);
    color: white;
    box-shadow: 0 4px 15px rgba(47,127,122,0.25);
  }

  /* Hover effect for labels */
  .segmented-control label:hover .segmented-text:not(.active) {
    color: var(--primary);
  }

  .assistant-intake .segmented-control label.has-required-star > span:first-child:not(.segmented-text) {
    display: none;
  }

  .assistant-intake-footer {
    display: flex;
    justify-content: flex-end;
    padding-top: 10px;
  }

  .assistant-intake-submit {
    padding: 16px 40px;
    border: none;
    border-radius: 18px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: #fff;
    font-size: 1.15rem;
    font-weight: 800;
    font-family: inherit;
    cursor: pointer;
    box-shadow: 0 10px 30px rgba(47,127,122,0.35);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .assistant-intake-submit:hover:not(:disabled) {
    transform: translateY(-3px) scale(1.03);
    box-shadow: 0 20px 40px rgba(47,127,122,0.45);
  }

  .assistant-intake-submit:active { transform: translateY(0); }

  .assistant-intake-submit svg {
    transition: transform 0.3s ease;
  }
  .assistant-intake-submit:hover svg {
    transform: translateX(-6px);
  }

  .chat-container {
    background: var(--bg-card);
    border-radius: 20px;
    border: 1px solid var(--border-color);
    box-shadow: 0 10px 30px -5px rgba(0,0,0,0.08);
    overflow: hidden;
    margin-top: 0;
  }
  .chat-header {
    background: linear-gradient(135deg, rgba(47,127,122,0.08), rgba(47,127,122,0.15));
    padding: 20px 24px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
  }

  .chat-title {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
  }

  .chat-title h2 {
    margin: 0;
    font-size: 1.4rem;
    color: var(--primary);
    font-weight: 700;
  }

  .robot-icon {
    width: 38px;
    height: 38px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
  }

  .chat-actions { display: flex; gap: 8px; }

  .action-btn {
    padding: 7px 14px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-main);
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
  }
  .action-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-1px);
  }

  .messages-area {
    height: 500px;
    overflow-y: auto;
    padding: 20px;
    background: var(--bg-page);
    scroll-behavior: smooth;
  }
  .messages-area::-webkit-scrollbar { width: 6px; }
  .messages-area::-webkit-scrollbar-track { background: transparent; }
  .messages-area::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; opacity: 0.4; }

  .message {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
    animation: slideIn 0.25s ease-out;
  }
  @keyframes slideIn {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .message.user { justify-content: flex-end; }

  .message-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.1rem;
  }
  .message.assistant .message-avatar { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
  .message.user .message-avatar { background: linear-gradient(135deg, #3b82f6, #1d4ed8); order: 2; }

  .message-content {
    max-width: 72%;
    padding: 12px 16px;
    border-radius: 16px;
  }
  .message.assistant .message-content {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    color: var(--text-main);
    border-radius: 4px 16px 16px 16px;
  }
  .message.user .message-content {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border-radius: 16px 4px 16px 16px;
    order: 1;
  }

  .message-text {
    font-size: 0.92rem;
    line-height: 1.7;
    margin: 0;
  }
  .message-text p { margin: 0 0 6px 0; }
  .message-text p:last-child { margin-bottom: 0; }
  .message-text a {
    color: var(--primary);
    text-decoration: underline;
    text-underline-offset: 2px;
  }
  [data-theme="dark"] .message-text a { color: #4ade80; }

  .message-timestamp {
    font-size: 0.72rem;
    color: var(--text-muted);
    margin-top: 4px;
    text-align: left;
  }
  .message.user .message-timestamp { text-align: right; color: rgba(255,255,255,0.65); }

  .typing-indicator { display: none; align-items: center; gap: 10px; }
  .typing-indicator.active { display: flex; }
  .typing-dots {
    display: flex; gap: 4px;
    padding: 10px 14px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
  }
  .typing-dot {
    width: 7px; height: 7px;
    background: var(--primary);
    border-radius: 50%;
    animation: bounce 1.4s infinite;
  }
  .typing-dot:nth-child(2) { animation-delay: 0.2s; }
  .typing-dot:nth-child(3) { animation-delay: 0.4s; }
  @keyframes bounce {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-7px); }
  }

  .quick-chips {
    padding: 12px 20px;
    background: var(--bg-card);
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }
  .chip {
    padding: 6px 14px;
    background: linear-gradient(135deg, rgba(47,127,122,0.08), rgba(47,127,122,0.15));
    border: 1px solid rgba(47,127,122,0.3);
    border-radius: 20px;
    color: var(--primary);
    font-size: 0.82rem;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    font-family: inherit;
    font-weight: 600;
  }
  .chip:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(47,127,122,0.3);
  }

  .input-area {
    padding: 16px 20px;
    background: var(--bg-card);
    border-top: 1px solid var(--border-color);
  }
  .input-form { display: flex; gap: 10px; align-items: flex-end; }
  .input-wrapper { flex: 1; }

  #user-input {
    width: 100%;
    padding: 12px 16px;
    background: var(--bg-page);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    color: var(--text-main);
    font-size: 0.95rem;
    font-family: inherit;
    resize: none;
    min-height: 46px;
    max-height: 120px;
    transition: all 0.2s;
    box-sizing: border-box;
  }
  #user-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(47,127,122,0.1);
  }
  #user-input::placeholder { color: var(--text-muted); }

  .send-btn {
    padding: 12px 22px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border: none;
    border-radius: 12px;
    color: white;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
    font-family: inherit;
    box-shadow: 0 4px 12px rgba(47,127,122,0.3);
  }
  .send-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(47,127,122,0.4);
  }
  .send-btn:disabled { opacity: 0.5; cursor: not-allowed; }

  .disclaimer {
    margin: 0;
    padding: 12px 20px;
    background: rgba(251,191,36,0.08);
    border-top: 1px solid rgba(251,191,36,0.25);
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .disclaimer-icon { font-size: 1.2rem; flex-shrink: 0; }
  .disclaimer-text { color: #b45309; font-size: 0.85rem; margin: 0; }

  @media (max-width: 768px) {
    .assistant-intake-grid {
      grid-template-columns: 1fr;
    }
    .messages-area { height: 380px; padding: 14px; }
    .message-content { max-width: 88%; }
    .send-btn { padding: 12px 16px; font-size: 0.9rem; }
    .assistant-hero { padding: 22px; }
  }

  .btn-floating-back {
    position: fixed;
    top: 85px;
    right: 24px;
    z-index: 150;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--bg-card);
    padding: 10px 18px;
    border-radius: 12px;
    font-weight: 700;
    color: var(--text-main);
    border: 1px solid var(--border-color);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    text-decoration: none;
    transition: all 0.2s;
    font-size: 0.95rem;
  }
  .btn-floating-back:hover {
    transform: translateY(-2px);
    background: var(--primary);
    color: white;
    border-color: var(--primary);
  }
  .btn-floating-back svg { transition: transform 0.2s; }
  .btn-floating-back:hover svg { transform: translateX(4px); }
</style>

<main class="container assistant-shell">
  <a href="../welcome.php" class="btn-floating-back">
    <span>رجوع</span>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <line x1="19" y1="12" x2="5" y2="12"></line>
        <polyline points="12 19 19 12 12 5"></polyline>
    </svg>
  </a>

  <section class="assistant-hero">
    <div>
      <h1>المساعد الذكي</h1>
      <p>ابدأ هنا أولاً. اسأل عن الأعراض، التغذية، الرياضة، أو أي معلومة عامة عن السكري، ثم انتقل بعد ذلك إلى صفحة التوعية إذا أردت قراءة الشروحات الكاملة والبطاقات التعليمية.</p>
    </div>
    <div class="assistant-hero-actions">
      <a href="awareness.php" class="assistant-link secondary">فتح التوعية الصحية</a>
      <a href="../welcome.php" class="assistant-link primary">الصفحة الرئيسية</a>
    </div>
  </section>

  <?php if ($show_assistant_intake): ?>
  <section class="assistant-intake">
    <h2>أسئلة سريعة قبل البدء</h2>
    <p>أجب عن هذه الأسئلة الثلاثة، وسأبدأ معك من نفس الصفحة بتوجيه أولي مناسب لتوفير أفضل المعلومات لك.</p>
    
    <form class="assistant-intake-form" id="assistant-intake-form">
      <div class="assistant-intake-grid">
        
        <!-- Question 1 -->
        <div class="assistant-intake-item">
          <span class="question-kicker">سؤال 1</span>
          <strong class="is-required">هل أنت مهتم بالسكري؟</strong>
          <div class="segmented-control">
            <label>
              <input type="radio" name="interest" value="نعم" required>
              <span class="segmented-text">نعم</span>
            </label>
            <label>
              <input type="radio" name="interest" value="لا">
              <span class="segmented-text">لا</span>
            </label>
          </div>
        </div>

        <!-- Question 2 -->
        <div class="assistant-intake-item">
          <span class="question-kicker">سؤال 2</span>
          <strong class="is-required">هل الأسرة عندها سكري؟</strong>
          <div class="segmented-control">
            <label>
              <input type="radio" name="family_history" value="نعم" required>
              <span class="segmented-text">نعم</span>
            </label>
            <label>
              <input type="radio" name="family_history" value="لا">
              <span class="segmented-text">لا</span>
            </label>
          </div>
        </div>

        <!-- Question 3 -->
        <div class="assistant-intake-item">
          <span class="question-kicker">سؤال 3</span>
          <strong class="is-required">هل عندك قياسات؟</strong>
          <div class="segmented-control">
            <label>
              <input type="radio" name="measurements" value="نعم" required>
              <span class="segmented-text">نعم</span>
            </label>
            <label>
              <input type="radio" name="measurements" value="لا">
              <span class="segmented-text">لا</span>
            </label>
          </div>
        </div>

      </div>

      <div class="assistant-intake-footer">
        <button type="submit" class="assistant-intake-submit" id="assistant-intake-submit">
          <span>ابدأ مع المساعد</span>
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 19 12 12 5"></polyline>
          </svg>
        </button>
      </div>
    </form>
  </section>
  <?php endif; ?>

  <section class="chat-container">
    <div class="chat-header">
      <div class="chat-title">
        <div class="robot-icon">🤖</div>
        <h2>اسأل المساعد الذكي</h2>
      </div>
      <div class="chat-actions">
        <button class="action-btn" onclick="copyLastReply()">نسخ الرد</button>
        <button class="action-btn" onclick="clearChat()">مسح</button>
      </div>
    </div>

    <div class="messages-area" id="messages-area">
      <div class="message assistant">
        <div class="message-avatar">🤖</div>
        <div class="message-content">
          <div class="message-text">
            <p><strong>مرحباً!</strong></p>
            <p>أنا المساعد الذكي المتخصص في التوعية الصحية عن مرض السكري. يمكنني مساعدتك في:</p>
            <p>• معلومات عن أنواع السكري<br>• نصائح غذائية وصحية<br>• فهم الأعراض والعلاج<br>• أي أسئلة أخرى عن السكري</p>
          </div>
          <div class="message-timestamp" id="welcome-time"></div>
        </div>
      </div>

      <div class="typing-indicator" id="typing-indicator">
        <div class="message-avatar" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">🤖</div>
        <div class="typing-dots">
          <div class="typing-dot"></div>
          <div class="typing-dot"></div>
          <div class="typing-dot"></div>
        </div>
      </div>
    </div>

    <div class="quick-chips">
      <span style="color: var(--text-muted); font-size: 0.82rem; align-self: center;">أسئلة سريعة:</span>
      <button class="chip" onclick="sendQuickQuestion('ما هي أعراض مرض السكري؟')">أعراض السكري</button>
      <button class="chip" onclick="sendQuickQuestion('ما هي التغذية الصحية لمريض السكري؟')">التغذية الصحية</button>
      <button class="chip" onclick="sendQuickQuestion('ما فائدة الرياضة لمريض السكري؟')">الرياضة والسكري</button>
      <button class="chip" onclick="sendQuickQuestion('ماذا أفعل عند انخفاض السكر؟')">انخفاض السكر</button>
      <button class="chip" onclick="sendQuickQuestion('متى يجب أن أراجع الطبيب؟')">متى أراجع الطبيب؟</button>
    </div>

    <div class="input-area">
      <p style="margin: 0 0 8px; color: var(--text-muted); font-size: 0.85rem;">أو اكتب سؤالك هنا:</p>
      <form class="input-form" id="chat-form" onsubmit="sendMessage(event)">
        <div class="input-wrapper">
          <textarea id="user-input" placeholder="اكتب سؤالك هنا... (Enter للإرسال، Shift+Enter لسطر جديد)" required rows="1"></textarea>
        </div>
        <button type="submit" class="send-btn" id="send-btn">إرسال</button>
      </form>
    </div>

    <div class="disclaimer">
      <div class="disclaimer-icon">⚠️</div>
      <p class="disclaimer-text"><strong>تنبيه:</strong> هذا المساعد للتوعية العامة فقط ولا يغني عن استشارة الطبيب المختص.</p>
    </div>
  </section>
</main>

<script>
const showAssistantIntake = <?php echo $show_assistant_intake ? 'true' : 'false'; ?>;
const messagesArea = document.getElementById('messages-area');
const userInput = document.getElementById('user-input');
const chatForm = document.getElementById('chat-form');
const sendBtn = document.getElementById('send-btn');
const typingIndicator = document.getElementById('typing-indicator');
const intakeForm = document.getElementById('assistant-intake-form');
const intakeSubmitBtn = document.getElementById('assistant-intake-submit');
let lastBotReply = '';
let isSending = false;

document.getElementById('welcome-time').textContent = new Date().toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });

function setSendingState(isBusy) {
  userInput.disabled = isBusy;
  sendBtn.disabled = isBusy;
  if (intakeSubmitBtn) {
    intakeSubmitBtn.disabled = isBusy;
  }
}

userInput.addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

userInput.addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    void submitMessage(userInput.value);
  }
});

async function sendMessage(event) {
  event.preventDefault();
  await submitMessage(userInput.value);
}

async function submitMessage(rawMessage, visibleMessage = '') {
  const message = String(rawMessage || '').trim();
  if (!message || isSending) return;

  const displayText = visibleMessage.trim() || message;
  isSending = true;
  addMessage(displayText, 'user');
  userInput.value = '';
  userInput.style.height = 'auto';
  setSendingState(true);
  typingIndicator.classList.add('active');
  scrollToBottom();

  try {
    const response = await fetch('../main/api/groq_chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message })
    });

    const raw = await response.text();
    let data = null;

    try {
      data = raw ? JSON.parse(raw) : null;
    } catch (parseError) {
      const compact = raw
        .replace(/<script\b[^>]*>.*?<\/script>/gis, ' ')
        .replace(/<style\b[^>]*>.*?<\/style>/gis, ' ')
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

      if (/Vercel Authentication|Authentication Required/i.test(compact)) {
        throw new Error('حماية Vercel تمنع الوصول إلى API. استخدم رابط Production أو عطّل Deployment Protection.');
      }

      throw new Error(compact.slice(0, 220) || 'استجابة غير صالحة من الخادم.');
    }

    if (!response.ok) {
      throw new Error((data && data.error) ? data.error : `HTTP ${response.status}`);
    }

    if (data.ok && data.text) {
      addMessage(data.text, 'assistant');
      lastBotReply = data.text;
    } else {
      addMessage(data.error || 'عذراً، حدث خطأ. الرجاء المحاولة مرة أخرى.', 'assistant', true);
    }
  } catch (error) {
    const detail = error && error.message ? error.message : 'تأكد من اتصالك بالإنترنت.';
    addMessage(`عذراً، فشل الاتصال بالمساعد: ${detail}`, 'assistant', true);
    console.error('Error:', error);
  } finally {
    typingIndicator.classList.remove('active');
    isSending = false;
    setSendingState(false);
    userInput.focus();
  }
}

function addMessage(text, sender, isError = false) {
  const messageDiv = document.createElement('div');
  messageDiv.className = `message ${sender}`;

  const avatar = document.createElement('div');
  avatar.className = 'message-avatar';
  avatar.textContent = sender === 'user' ? '👤' : '🤖';

  const content = document.createElement('div');
  content.className = 'message-content';

  const messageText = document.createElement('div');
  messageText.className = 'message-text';

  if (sender === 'assistant' && !isError) {
    const escaped = text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\n/g, '<br>');
    messageText.innerHTML = escaped;
  } else {
    const p = document.createElement('p');
    p.textContent = text;
    messageText.appendChild(p);
  }

  const timestamp = document.createElement('div');
  timestamp.className = 'message-timestamp';
  timestamp.textContent = new Date().toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });

  content.appendChild(messageText);
  content.appendChild(timestamp);
  messageDiv.appendChild(avatar);
  messageDiv.appendChild(content);
  messagesArea.insertBefore(messageDiv, typingIndicator);
  scrollToBottom();
}

function sendQuickQuestion(question) {
  void submitMessage(question);
}

async function handleAssistantIntake(event) {
  event.preventDefault();
  if (!intakeForm) return;

  const formData = new FormData(intakeForm);
  const interest = String(formData.get('interest') || '').trim();
  const familyHistory = String(formData.get('family_history') || '').trim();
  const measurements = String(formData.get('measurements') || '').trim();

  if (!interest || !familyHistory || !measurements) {
    return;
  }

  const visibleMessage = [
    'أحتاج توجيهاً أولياً عن السكري.',
    `هل أنت مهتم بالسكري؟ ${interest}`,
    `هل الأسرة عندها سكري؟ ${familyHistory}`,
    `هل عندك قياسات؟ ${measurements}`
  ].join('\n');

  const prompt = [
    'أنا في صفحة المساعد الذكي وأحتاج توجيهاً أولياً عن السكري.',
    `هل أنت مهتم بالسكري؟ ${interest}`,
    `هل الأسرة عندها سكري؟ ${familyHistory}`,
    `هل عندك قياسات؟ ${measurements}`,
    'قم بطمأنتي أولاً بصفتك مساعداً ذكياً، ثم أعطني توجيهاً أولياً مختصراً وواضحاً. لا تركز فقط على طلب القياسات، بل أجب عن التساؤلات العامة التي قد تجول في خاطري بناءً على إجاباتي السابقة وكن داعماً لي.'
  ].join('\n');

  await submitMessage(prompt, visibleMessage);
}

function clearChat() {
  if (confirm('هل أنت متأكد من مسح المحادثة؟')) {
    const messages = messagesArea.querySelectorAll('.message');
    messages.forEach((msg, index) => {
      if (index > 0) msg.remove();
    });
    lastBotReply = '';
  }
}

function copyLastReply() {
  if (!lastBotReply) {
    alert('لا يوجد رد لنسخه');
    return;
  }

  navigator.clipboard.writeText(lastBotReply).then(() => {
    alert('تم نسخ الرد إلى الحافظة');
  }).catch(() => {
    alert('فشل نسخ الرد');
  });
}

function scrollToBottom() {
  setTimeout(() => {
    messagesArea.scrollTop = messagesArea.scrollHeight;
  }, 100);
}

if (showAssistantIntake && intakeForm) {
  intakeForm.addEventListener('submit', handleAssistantIntake);
}

scrollToBottom();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
