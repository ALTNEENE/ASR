// tools.js - Handle glucose reading form, history, medications, and AI suggestion
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('glucoseForm');
    const resultDiv = document.getElementById('result');
    const guidanceDiv = document.getElementById('guidance');
    const historyBody = document.getElementById('history-body');
    const readingInput = document.getElementById('reading');
    const unitSelect = document.getElementById('unit');
    const contextSelect = document.getElementById('context');
    const dateInput = document.getElementById('reading-date');
    const timeInput = document.getElementById('reading-time');
    const noteInput = document.getElementById('reading-note');
    const medInput = document.getElementById('medications');

    const pdfBtn = document.getElementById('generate-pdf');
    const whatsappBtn = document.getElementById('whatsapp-doctor');
    const emailBtn = document.getElementById('email-doctor');

    let currentReadings = [];
    const MIN_MGDL = 20;
    const MAX_MGDL = 600;
    const MIN_MMOL = 1.1;
    const MAX_MMOL = 33.3;

    // === Medication State ===
    // Tracks selected medications as { id, name_ar, name_en }
    let selectedMeds = [];
    // Current AI suggestion data 
    let aiSuggestionData = null;
    let aiDebounceTimer = null;

    // Set default date and time
    // Removed to keep fields empty

    // Load readings and saved meds on page load
    if (historyBody) loadReadings();
    loadSavedMedications();

    // === Autocomplete setup ===
    if (medInput) {
        enableAutocomplete(medInput, 'api/search_drugs.php', function (item) {
            // When user picks from autocomplete dropdown
            if (item && item.id) {
                addSelectedMed(item.id, item.name_ar, item.name_en);
                medInput.value = '';
                hideAiSuggestion();
            }
        });

        // AI suggestion on input (debounced)
        medInput.addEventListener('input', function () {
            const q = this.value.trim();
            clearTimeout(aiDebounceTimer);
            hideAiSuggestion();

            if (q.length >= 2) {
                aiDebounceTimer = setTimeout(() => fetchAiSuggestion(q), 400);
            }
        });
    }

    // === AI Suggestion Functions ===
    function fetchAiSuggestion(q) {
        fetch('api/suggest_medication.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ q: q })
        })
            .then(r => r.json())
            .then(data => {
                if (data.ok && data.best_id && data.confidence >= 0.6) {
                    showAiSuggestion(data);
                } else if (data.ok && (!data.best_id || data.confidence < 0.6)) {
                    showNoMatch();
                }
                // If not ok, silently fail — dropdown is still active
            })
            .catch(err => {
                console.error('AI suggestion error:', err);
            });
    }

    function showAiSuggestion(data) {
        aiSuggestionData = data;
        const el = document.getElementById('ai-suggestion');
        const nameEl = document.getElementById('ai-suggestion-name');
        const reasonEl = document.getElementById('ai-suggestion-reason');
        const noMatchEl = document.getElementById('ai-no-match');

        if (el && nameEl) {
            nameEl.textContent = data.best_name_ar + ' (' + data.best_name_en + ')';
            if (reasonEl && data.reason_ar) {
                reasonEl.textContent = data.reason_ar;
            }
            el.style.display = 'block';
            el.onclick = function () { acceptAiSuggestion(); };
        }
        if (noMatchEl) noMatchEl.style.display = 'none';
    }

    function showNoMatch() {
        const el = document.getElementById('ai-suggestion');
        const noMatchEl = document.getElementById('ai-no-match');
        if (el) el.style.display = 'none';
        if (noMatchEl) noMatchEl.style.display = 'block';
        // Auto-hide after 3 seconds
        setTimeout(() => { if (noMatchEl) noMatchEl.style.display = 'none'; }, 3000);
    }

    function hideAiSuggestion() {
        const el = document.getElementById('ai-suggestion');
        const noMatchEl = document.getElementById('ai-no-match');
        if (el) el.style.display = 'none';
        if (noMatchEl) noMatchEl.style.display = 'none';
        aiSuggestionData = null;
    }

    // Make globally available for inline onclick
    window.acceptAiSuggestion = function () {
        if (aiSuggestionData && aiSuggestionData.best_id) {
            addSelectedMed(
                aiSuggestionData.best_id,
                aiSuggestionData.best_name_ar,
                aiSuggestionData.best_name_en
            );
            if (medInput) medInput.value = '';
            hideAiSuggestion();
        }
    };

    // === Selected Medications Chip Management ===
    function addSelectedMed(id, nameAr, nameEn) {
        id = parseInt(id);
        // Don't add duplicates
        if (selectedMeds.some(m => m.id === id)) return;

        selectedMeds.push({ id, name_ar: nameAr, name_en: nameEn });
        renderSelectedMeds();
        // Immediately save to DB
        saveMedicationToDB(id);
    }

    function removeSelectedMed(id) {
        selectedMeds = selectedMeds.filter(m => m.id !== id);
        renderSelectedMeds();
    }

    function renderSelectedMeds() {
        const container = document.getElementById('selected-medications');
        if (!container) return;

        if (selectedMeds.length === 0) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = selectedMeds.map(m => `
            <div class="med-chip" data-id="${m.id}">
                <span>${m.name_ar}</span>
                <span class="chip-remove" onclick="window.removeMedChip(${m.id})" title="إزالة">×</span>
            </div>
        `).join('');
    }

    window.removeMedChip = function (id) {
        removeSelectedMed(id);
    };

    // === Save Medication to DB ===
    function saveMedicationToDB(medId) {
        fetch('api/save_user_medication.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ medication_id: medId })
        })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    // Refresh the saved medications list
                    loadSavedMedications();
                } else {
                    console.error('Save medication error:', data.error);
                }
            })
            .catch(err => console.error('Save medication fetch error:', err));
    }

    // === Load & Render Saved Medications ===
    function loadSavedMedications() {
        const listEl = document.getElementById('saved-meds-list');
        if (!listEl) return;

        fetch('api/get_user_medications.php')
            .then(r => r.json())
            .then(data => {
                if (data.ok && data.medications) {
                    renderSavedMedications(data.medications);
                } else {
                    listEl.innerHTML = '<div style="text-align:center; padding:20px; color:#94a3b8;">لا توجد أدوية محفوظة</div>';
                }
            })
            .catch(err => {
                listEl.innerHTML = '<div style="text-align:center; padding:20px; color:#b91c1c;">فشل تحميل الأدوية</div>';
                console.error('Load meds error:', err);
            });
    }

    function renderSavedMedications(meds) {
        const listEl = document.getElementById('saved-meds-list');
        if (!listEl) return;

        if (meds.length === 0) {
            listEl.innerHTML = '<div style="text-align:center; padding:20px; color:#94a3b8;">لا توجد أدوية محفوظة بعد. ابحث عن دواء أعلاه لإضافته.</div>';
            return;
        }

        listEl.innerHTML = meds.map(m => {
            const typeIcons = {
                'tablet': '💊',
                'insulin': '💉',
                'injection': '💉'
            };
            const icon = typeIcons[m.type] || '💊';

            return `
                <div class="saved-med-item" data-umid="${m.um_id}">
                    <div>
                        <span style="font-size:1.1rem;">${icon}</span>
                        <strong style="margin:0 6px;">${m.name_ar}</strong>
                        <span style="color:var(--muted, #888); font-size:0.85rem;">${m.name_en}</span>
                        ${m.dose ? `<span style="font-size:0.8rem; color:#64748b; margin-right:8px;">الجرعة: ${m.dose}</span>` : ''}
                    </div>
                    <button class="saved-med-delete" onclick="window.deleteUserMed(${m.um_id})">حذف</button>
                </div>
            `;
        }).join('');
    }

    window.deleteUserMed = function (umId) {
        if (!confirm('هل تريد حذف هذا الدواء من قائمتك؟')) return;

        fetch('api/delete_user_medication.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ um_id: umId })
        })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    loadSavedMedications();
                } else {
                    alert('فشل الحذف: ' + (data.error || ''));
                }
            })
            .catch(err => console.error('Delete med error:', err));
    };

    // === Reading Input Constraints ===
    function setReadingConstraints() {
        if (!readingInput || !unitSelect) return;
        if (unitSelect.value === 'mmol') {
            readingInput.min = String(MIN_MMOL);
            readingInput.max = String(MAX_MMOL);
            readingInput.placeholder = '';
        } else {
            readingInput.min = String(MIN_MGDL);
            readingInput.max = String(MAX_MGDL);
            readingInput.placeholder = '';
        }
    }

    setReadingConstraints();
    if (unitSelect) {
        unitSelect.addEventListener('change', setReadingConstraints);
    }

    // === Handle Form Submission ===
    let isSubmitting = false; // guard against double-submit
    if (form) form.addEventListener('submit', async function (e) {
        e.preventDefault();

        if (isSubmitting) return; // block duplicate submission

        if (!readingInput || !unitSelect) return;
        const readingValue = parseFloat(readingInput.value);
        if (!Number.isFinite(readingValue) || readingValue <= 0) {
            alert('الرجاء إدخال قيمة قراءة صحيحة.');
            return;
        }
        const minAllowed = unitSelect.value === 'mmol' ? MIN_MMOL : MIN_MGDL;
        const maxAllowed = unitSelect.value === 'mmol' ? MAX_MMOL : MAX_MGDL;
        if (readingValue < minAllowed) {
            const unitLabel = unitSelect.value === 'mmol' ? 'mmol/L' : 'mg/dL';
            alert(`القيمة غير واقعية. الحد الأدنى المسموح هو ${minAllowed} ${unitLabel}.`);
            return;
        }
        if (readingValue > maxAllowed) {
            const unitLabel = unitSelect.value === 'mmol' ? 'mmol/L' : 'mg/dL';
            alert(`القيمة غير واقعية. الحد الأقصى المسموح هو ${maxAllowed} ${unitLabel}.`);
            return;
        }

        const formData = new FormData(form);
        if (readingInput) formData.set('reading', readingInput.value);
        if (unitSelect) formData.set('unit', unitSelect.value);
        if (contextSelect) formData.set('context', contextSelect.value);
        if (dateInput) formData.set('reading_date', dateInput.value);
        if (timeInput) formData.set('reading_time', timeInput.value);
        if (noteInput) formData.set('note', noteInput.value);

        // Send selected medication names as text for backward compatibility
        const medNames = selectedMeds.map(m => m.name_ar).join('، ');
        formData.set('medications', medNames);

        const psychInput = document.getElementById('psychological_status');
        if (psychInput) formData.set('psychological_status', psychInput.value);

        // New optional fields
        const weightInput = document.getElementById('weight_kg');
        if (weightInput && weightInput.value !== '') formData.set('weight_kg', weightInput.value);

        const lastDoseInput = document.getElementById('last_medication_dose');
        if (lastDoseInput && lastDoseInput.value !== '') formData.set('last_medication_dose', lastDoseInput.value);

        // Disable submit button to prevent double-click
        isSubmitting = true;
        const submitBtn = form.querySelector('[type="submit"]');
        const originalBtnText = submitBtn ? submitBtn.textContent : '';
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'جاري الحفظ...'; }

        try {
            const response = await fetch('api/save_reading.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                displayResult(data.status_ar || data.classification, formData.get('reading'));
                await loadReadings();
                form.reset();
                // Not preserving current date automatically, keeping empty
                // Clear selected meds chips (already saved to DB individually)
                selectedMeds = [];
                renderSelectedMeds();
            } else {
                alert('خطأ: ' + data.error);
            }
        } catch (error) {
            alert('حدث خطأ في الاتصال');
            console.error(error);
        } finally {
            // Re-enable submit button
            isSubmitting = false;
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalBtnText; }
        }
    });

    function normalizeClassificationText(value) {
        let text = String(value || '');
        if (text.toLowerCase().includes('prediab')) return 'طبيعي';
        if (text === 'ما قبل السكري') return 'طبيعي';
        return text;
    }

    // === Display Result ===
    function displayResult(classification, value) {
        if (resultDiv) resultDiv.hidden = false;
        if (guidanceDiv) guidanceDiv.hidden = false;

        classification = normalizeClassificationText(classification);
        let color, message, dietTips, avoidTips;

        if (classification.includes('مرتفع')) {
            color = '#b91c1c';
            message = `⚠️ القراءة مرتفعة (${value})`;
            dietTips = [
                'اشرب كميات كبيرة من الماء',
                'تجنب السكريات والنشويات',
                'مارس رياضة المشي الخفيف'
            ];
            avoidTips = [
                'العصائر المحلاة',
                'الحلويات والمخبوزات البيضاء',
                'التوتر والانفعال'
            ];
        } else if (classification.includes('منخفض')) {
            color = '#b45309';
            message = `📉 القراءة منخفضة (${value})`;
            dietTips = [
                'تناول 15 جم من السكر (نصف كوب عصير)',
                'انتظر 15 دقيقة وأعد القياس',
                'تناول وجبة خفيفة'
            ];
            avoidTips = [
                'القيادة أو تشغيل الآلات',
                'تجاهل الأعراض',
                'النوم مباشرة'
            ];
        } else {
            color = '#047857';
            message = `✅ القراءة طبيعية (${value})`;
            dietTips = [
                'حافظ على نظامك الغذائي المتوازن',
                'استمر في النشاط البدني المعتدل'
            ];
            avoidTips = [
                'الإفراط في تناول الطعام',
                'الخمول لفترات طويلة'
            ];
        }

        resultDiv.style.backgroundColor = color + '20';
        resultDiv.style.color = color;
        resultDiv.style.border = `1px solid ${color}`;
        resultDiv.innerHTML = `<strong>${message}</strong>`;

        const dietList = document.getElementById('diet-tips');
        const avoidList = document.getElementById('avoid-tips');
        const linkList = document.getElementById('link-tips');

        if (dietList) {
            dietList.innerHTML = dietTips.map(tip => `<li>${tip}</li>`).join('');
        }

        if (avoidList) {
            avoidList.innerHTML = avoidTips.map(tip => `<li>${tip}</li>`).join('');
        }

        if (linkList) {
            linkList.innerHTML = `
                <li><a href="../Awareness/awareness.php" target="_blank">📚 دليل التغذية السليمة</a></li>
            `;
        }
    }

    // === Load Reading History ===
    async function loadReadings() {
        if (!historyBody) return;

        try {
            historyBody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 20px;">جاري التحميل...</td></tr>';

            const response = await fetch('api/get_readings.php?limit=100');
            const data = await response.json();

            if (data.success) {
                currentReadings = data.readings;
                renderReadings(data.readings);
            } else {
                historyBody.innerHTML = `<tr><td colspan="8" style="text-align:center; color:red;">خطأ: ${data.error}</td></tr>`;
            }
        } catch (error) {
            historyBody.innerHTML = `<tr><td colspan="8" style="text-align:center; color:red;">فشل الاتصال: ${error.message}</td></tr>`;
        }
    }

    function renderReadings(readings) {
        if (!historyBody) return;

        if (readings.length === 0) {
            historyBody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 20px; color: #666;">لا توجد قراءات مسجلة بعد</td></tr>';
            return;
        }

        historyBody.innerHTML = readings.map(r => {
            const normalizedClass = normalizeClassificationText(r.status_ar || r.classification);
            let statusClass = 'status-normal';
            if (normalizedClass && normalizedClass.includes('مرتفع')) statusClass = 'status-danger';
            if (normalizedClass && normalizedClass.includes('منخفض')) statusClass = 'status-warning';

            const contextMap = {
                'fasting': '🌙 صائم',
                'post': '🍽️ بعد الأكل',
                'ramadan': '🌙 صائم',
                'random': '🎲 عشوائي'
            };

            const psychMap = {
                'Normal': '😐 طبيعي',
                'Happy': '🙂 سعيد',
                'Stressed': '😫 متوتر',
                'Sad': '😔 حزين',
                'Sick': '🤒 مريض'
            };

            return `
            <tr>
                <td>${r.reading_date}</td>
                <td>${r.reading_time}</td>
                <td style="font-weight:bold">${r.reading_value} ${r.reading_unit}</td>
                <td>${contextMap[r.reading_context] || r.reading_context}</td>
                <td><span class="status-badge ${statusClass}">${normalizedClass || '-'}</span></td>
                <td style="color:#666">${psychMap[r.psychological_status] || r.psychological_status || '-'}</td>
                <td style="color:#666">${r.note || '-'}</td>
                <td style="color:#666">${r.medications || '-'}</td>
            </tr>
            `;
        }).join('');
    }

    // === Share actions ===
    if (pdfBtn) {
        pdfBtn.addEventListener('click', function () {
            window.open('report.php', '_blank');
        });
    }

    if (whatsappBtn) {
        whatsappBtn.addEventListener('click', function () {
            const summary = currentReadings.slice(0, 5).map(r =>
                `- ${r.reading_date} ${r.reading_time}: ${r.reading_value} ${r.reading_unit} (${normalizeClassificationText(r.status_ar || r.classification)})`
            ).join('%0a');

            const text = `تقرير سكر الدم:%0a${summary}%0a%0aتم الإنشاء بواسطة نظام متابعة السكري.`;
            window.open(`https://wa.me/?text=${text}`, '_blank');
        });
    }

    if (emailBtn) {
        emailBtn.addEventListener('click', function () {
            const summary = currentReadings.slice(0, 5).map(r =>
                `- ${r.reading_date} ${r.reading_time}: ${r.reading_value} ${r.reading_unit} (${normalizeClassificationText(r.status_ar || r.classification)})`
            ).join('%0d%0a');

            const subject = "تقرير متابعة سكر الدم";
            const body = `مرحباً، إليك أحدث قراءات سكر الدم:%0d%0a%0d%0a${summary}%0d%0a%0d%0aتحياتي.`;

            window.open(`mailto:?subject=${subject}&body=${body}`, '_blank');
        });
    }
});
