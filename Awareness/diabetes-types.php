<?php
$base_path = '../';
$page_title = 'أنواع السكري - A.S.R';
$figma_page = 'awareness';
include __DIR__ . '/../includes/header.php';
?>

<main class="container" style="padding: 40px 0;">
  
  <div class="card" style="margin-bottom: 30px;">
    <h1 style="margin: 0 0 10px 0; color: var(--teal);">الأنواع الرئيسية للسكري</h1>
    <p style="color: var(--muted); margin: 0; font-size: 18px;">التشخيص الدقيق يساعد على اختيار الخطة العلاجية المناسبة لكل حالة.</p>
  </div>

  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
    
    <div class="card">
      <h3 style="color: var(--teal); margin-top: 0;">النوع الأول (Type 1)</h3>
      <p style="line-height: 1.6;">نقص شديد أو انعدام تام في إنتاج الإنسولين من البنكرياس. يحدث غالباً في سن مبكرة ويحتاج المريض عادةً لحقن الإنسولين مدى الحياة والمتابعة الدقيقة لمستويات السكر.</p>
    </div>

    <div class="card">
      <h3 style="color: var(--teal); margin-top: 0;">النوع الثاني (Type 2)</h3>
      <p style="line-height: 1.6;">يحدث بسبب مقاومة الجسم للإنسولين أو عدم كفاية إفرازه. يرتبط بشكل كبير بنمط الحياة والوراثة والوزن الزائد. يمكن التحكم به غالباً عبر النظام الغذائي، الرياضة، والأدوية الفموية.</p>
    </div>

    <div class="card">
      <h3 style="color: var(--teal); margin-top: 0;">سكري الحمل</h3>
      <p style="line-height: 1.6;">ارتفاع سكر الدم الذي يظهر لأول مرة أثناء الحمل. يحتاج لمراقبة دقيقة للحفاظ على صحة الأم والجنين، وغالباً ما يختفي بعد الولادة لكنه قد يزيد خطر الإصابة بالسكري مستقبلاً.</p>
    </div>

    <div class="card">
      <h3 style="color: var(--teal); margin-top: 0;">ما قبل السكري</h3>
      <p style="line-height: 1.6;">حالة يكون فيها مستوى السكر أعلى من الطبيعي لكن لم يصل لمرحلة التشخيص بالسكري. تعتبر فرصة ذهبية لتغيير نمط الحياة ومنع تطور الحالة إلى النوع الثاني.</p>
    </div>

  </div>

  <div class="card" style="margin-top: 30px;">
    <h2 style="color: var(--teal); margin-top: 0;">الأنواع المتفرعة</h2>
    <ul style="line-height: 1.9; padding-right: 22px; margin-bottom: 0;">
      <li><strong>سكري الأطفال:</strong> يتفرع غالباً من النوع الأول، وهو الأكثر شيوعاً عند الأطفال، وأحياناً قد يكون من النوع الثاني.</li>
      <li><strong>سكري البالغين المبكر (LADA - Latent Autoimmune Diabetes in Adults):</strong> يشبه النوع الأول لكنه يظهر في سن الشباب أو البالغين.</li>
      <li><strong>سكري الحمل:</strong> حالة خاصة مرتبطة بالحمل، ويُعامل كفرع مستقل ضمن التصنيف.</li>
      <li><strong>سكري ثانوي (Secondary Diabetes):</strong> يظهر نتيجة أمراض أو أدوية تؤثر على البنكرياس، مثل التهاب البنكرياس أو استخدام الكورتيزون لفترات طويلة.</li>
      <li><strong>سكري أحادي الجين (Monogenic Diabetes):</strong> نوع نادر سببه خلل وراثي في جين واحد، مثل MODY - Maturity Onset Diabetes of the Young.</li>
      <li><strong>سكري الماء (Diabetes Insipidus):</strong> يختلف عن السكري المعروف، وسببه خلل في هرمون ADH مما يؤدي إلى فقدان كميات كبيرة من الماء عبر البول.</li>
    </ul>
  </div>
  
  <div style="margin-top: 30px; text-align: center;">
    <a href="awareness.php" class="btn btn-secondary" data-back-button>← العودة لصفحة التوعية</a>
  </div>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
