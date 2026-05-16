<?php

function awareness_article_defaults(): array
{
    return [
        [
            'slug' => 'types',
            'title_ar' => 'أنواع السكري',
            'summary_ar' => 'تعرف على الفروقات الأساسية بين النوع الأول والثاني وسكري الحمل وكيفية التعامل مع كل نوع.',
            'body_html_ar' => <<<HTML
<h3 style="color: var(--primary); margin-top: 0;">النوع الأول</h3>
<p>يحدث عندما يتوقف البنكرياس عن إنتاج الإنسولين بشكل كافٍ، وغالباً يحتاج المريض إلى الإنسولين والمتابعة الدقيقة المنتظمة.</p>
<h3 style="color: var(--primary);">النوع الثاني</h3>
<p>هو الأكثر شيوعاً، ويرتبط بمقاومة الجسم للإنسولين أو انخفاض كفاءته، ويتحسن عادةً مع الغذاء الصحي، النشاط البدني، والأدوية المناسبة.</p>
<h3 style="color: var(--primary);">سكري الحمل</h3>
<p>يظهر أثناء الحمل ويحتاج إلى متابعة دقيقة لحماية الأم والجنين، وقد يختفي بعد الولادة لكنه يتطلب الانتباه مستقبلاً.</p>
<h3 style="color: var(--primary);">الأنواع المتفرعة</h3>
<ul style="padding-right: 20px; color: var(--text-main); line-height: 1.9;">
  <li><strong>سكري الأطفال:</strong> يتفرع غالباً من النوع الأول، وهو الأكثر شيوعاً عند الأطفال، وأحياناً قد يكون من النوع الثاني.</li>
  <li><strong>سكري البالغين المبكر (LADA - Latent Autoimmune Diabetes in Adults):</strong> يشبه النوع الأول لكنه يظهر في سن الشباب أو البالغين.</li>
  <li><strong>سكري الحمل:</strong> حالة خاصة مرتبطة بالحمل، ويُعامل كفرع مستقل ضمن التصنيف.</li>
  <li><strong>سكري ثانوي (Secondary Diabetes):</strong> يظهر نتيجة أمراض أو أدوية تؤثر على البنكرياس، مثل التهاب البنكرياس أو استخدام الكورتيزون لفترات طويلة.</li>
  <li><strong>سكري أحادي الجين (Monogenic Diabetes):</strong> نوع نادر سببه خلل وراثي في جين واحد، مثل MODY - Maturity Onset Diabetes of the Young.</li>
  <li><strong>سكري الماء (Diabetes Insipidus):</strong> يختلف عن السكري المعروف، وسببه خلل في هرمون ADH مما يؤدي إلى فقدان كميات كبيرة من الماء عبر البول.</li>
</ul>
HTML,
            'sort_order' => 10,
        ],
        [
            'slug' => 'nutrition',
            'title_ar' => 'التغذية السليمة',
            'summary_ar' => 'أهمية النظام الغذائي المتوازن، حساب الكربوهيدرات، والأطعمة المناسبة لضبط مستوى السكر.',
            'body_html_ar' => <<<HTML
<h3 style="color: var(--primary); margin-top: 0;">أساسيات التغذية</h3>
<p>التركيز على الخضروات، الحبوب الكاملة، البروتينات الجيدة، وتقليل السكريات البسيطة يساعد على استقرار السكر.</p>
<h3 style="color: var(--primary);">تنظيم الوجبات</h3>
<p>توزيع الوجبات خلال اليوم والالتزام بكميات معتدلة يقلل الارتفاعات المفاجئة في سكر الدم.</p>
<h3 style="color: var(--primary);">نصائح عملية</h3>
<ul style="padding-right: 20px; color: var(--text-main); line-height: 1.9;">
  <li>اختر أطعمة غنية بالألياف.</li>
  <li>قلل العصائر المحلاة والمشروبات الغازية.</li>
  <li>راقب الكربوهيدرات خصوصاً مع الوجبات الكبيرة.</li>
</ul>
HTML,
            'sort_order' => 20,
        ],
        [
            'slug' => 'activity',
            'title_ar' => 'النشاط البدني',
            'summary_ar' => 'دور الرياضة في تحسين حساسية الإنسولين وضبط الوزن، ونصائح لممارسة الرياضة بأمان.',
            'body_html_ar' => <<<HTML
<h3 style="color: var(--primary); margin-top: 0;">لماذا الرياضة مهمة؟</h3>
<p>النشاط البدني المنتظم يساعد الجسم على استخدام الإنسولين بكفاءة أفضل، ويحسن اللياقة العامة ويقلل عوامل الخطورة.</p>
<h3 style="color: var(--primary);">المدة المقترحة</h3>
<p>يفضل الوصول إلى 150 دقيقة أسبوعياً من النشاط المعتدل مثل المشي السريع أو الدراجة.</p>
<h3 style="color: var(--primary);">قبل التمرين</h3>
<ul style="padding-right: 20px; color: var(--text-main); line-height: 1.9;">
  <li>افحص السكر إذا كنت تستخدم الإنسولين أو لديك نوبات انخفاض.</li>
  <li>احمل معك وجبة خفيفة أو مصدر سكر سريع.</li>
  <li>ابدأ تدريجياً إذا كنت غير معتاد على الرياضة.</li>
</ul>
HTML,
            'sort_order' => 30,
        ],
        [
            'slug' => 'sugar-levels',
            'title_ar' => 'معدل السكر حسب العمر',
            'summary_ar' => 'تعرف على المستويات الطبيعية للسكر في الدم بصورة عامة، ومتى تحتاج لمراجعة الطبيب أو زيادة المتابعة.',
            'body_html_ar' => <<<HTML
<h3 style="color: var(--primary); margin-top: 0;">فكرة عامة</h3>
<p>المستوى المناسب يختلف حسب العمر، التاريخ المرضي، ونوع السكري. لذلك تؤخذ الأرقام كمرجع عام وليس كبديل عن تقييم الطبيب.</p>
<h3 style="color: var(--primary);">ملاحظات مهمة</h3>
<ul style="padding-right: 20px; color: var(--text-main); line-height: 1.9;">
  <li>الأهداف تكون عادةً أكثر مرونة لدى كبار السن أو من لديهم حالات صحية أخرى.</li>
  <li>السكر بعد الوجبات مهم مثل سكر الصيام، خاصة في المتابعة اليومية.</li>
  <li>السكر التراكمي يعطي صورة عن التحكم خلال الأشهر السابقة.</li>
</ul>
<p>إذا كانت القراءات تتكرر خارج النطاق المعتاد، فذلك يعني أن الخطة العلاجية أو الغذائية تحتاج مراجعة.</p>
HTML,
            'sort_order' => 40,
        ],
        [
            'slug' => 'organs-impact',
            'title_ar' => 'تأثير السكري على الأعضاء',
            'summary_ar' => 'تعرف على المضاعفات المحتملة على القلب والكلى والعين والأعصاب، وكيفية الوقاية منها مبكراً.',
            'body_html_ar' => <<<HTML
<h3 style="color: var(--primary); margin-top: 0;">الأعضاء الأكثر تأثراً</h3>
<ul style="padding-right: 20px; color: var(--text-main); line-height: 1.9;">
  <li>القلب والأوعية الدموية.</li>
  <li>الكلى.</li>
  <li>العين والشبكية.</li>
  <li>الأعصاب الطرفية والقدمين.</li>
</ul>
<h3 style="color: var(--primary);">كيف تقلل المخاطر؟</h3>
<p>الالتزام بالدواء، ضبط السكر والضغط، الفحص الدوري، والانتباه لأي تغيّر في النظر أو الإحساس في القدمين يقلل المضاعفات بشكل كبير.</p>
HTML,
            'sort_order' => 50,
        ],
    ];
}

function awareness_ensure_table_and_seed(mysqli $conn): void
{
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS awareness_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    title_ar VARCHAR(255) NOT NULL,
    summary_ar TEXT NOT NULL,
    body_html_ar LONGTEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    $conn->query($sql);

    $count = 0;
    $result = $conn->query('SELECT COUNT(*) AS total FROM awareness_articles');
    if ($result instanceof mysqli_result) {
        $row = $result->fetch_assoc();
        $count = (int)($row['total'] ?? 0);
        $result->free();
    }

    if ($count > 0) {
        foreach (awareness_article_defaults() as $article) {
            if (($article['slug'] ?? '') !== 'types') {
                continue;
            }
            $syncStmt = $conn->prepare('UPDATE awareness_articles SET summary_ar = ?, body_html_ar = ?, updated_at = NOW() WHERE slug = ?');
            if ($syncStmt) {
                $syncStmt->bind_param('sss', $article['summary_ar'], $article['body_html_ar'], $article['slug']);
                $syncStmt->execute();
                $syncStmt->close();
            }
            break;
        }
        return;
    }

    $stmt = $conn->prepare(
        'INSERT INTO awareness_articles (slug, title_ar, summary_ar, body_html_ar, sort_order) VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return;
    }

    foreach (awareness_article_defaults() as $article) {
        $stmt->bind_param(
            'ssssi',
            $article['slug'],
            $article['title_ar'],
            $article['summary_ar'],
            $article['body_html_ar'],
            $article['sort_order']
        );
        $stmt->execute();
    }

    $stmt->close();
}

function awareness_fetch_articles(mysqli $conn): array
{
    $articles = [];
    $sql = 'SELECT slug, title_ar, summary_ar, body_html_ar FROM awareness_articles WHERE is_active = 1 ORDER BY sort_order ASC, id ASC';
    $result = $conn->query($sql);
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $articles[] = $row;
        }
        $result->free();
    }

    return $articles;
}
