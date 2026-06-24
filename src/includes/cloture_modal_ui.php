<!-- MODALE DE CLÔTURE UNIQUE -->
<div id="clotureModal" class="modal" style="display:none;">
    <div class="modal-content" style="margin:2% auto; padding:25px; width:90%; max-width:800px; max-height:95vh; overflow-y:auto;">
        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.35rem;"><i class="fa-solid fa-file-signature"></i> Clôturer l'Intervention #<span id="clotureInterventionIdText"></span></h3>
            <span class="close" onclick="closeClotureModal()">&times;</span>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="cloturer_modal">
            <input type="hidden" name="intervention_id" id="clotureInterventionId">
            
            <div class="alert alert-warning" style="margin-bottom: 20px;">
                <strong><i class="fa-solid fa-robot"></i> Assistant IA Activé :</strong> Cliquez sur "🪄 Améliorer (IA)" pour le reformuler professionnellement le brouillon du technicien.
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 5px;">
                    <label for="rapport_dispatch" style="font-weight:bold; margin:0;"><i class="fa-solid fa-pen-to-square"></i> Rapport Complet (Sera envoyé au client)</label>
                    <button type="button" class="btn btn-sm" onclick="rewriteClotureText('rapport_dispatch')">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Améliorer (IA)
                    </button>
                </div>
                <textarea name="rapport_dispatch" id="rapport_dispatch" class="form-control" rows="8" required placeholder="Saisissez le rapport complet de l'intervention..."></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="message_email" style="font-weight:bold; margin-bottom: 5px; display:block;"><i class="fa-solid fa-envelope-open-text"></i> Message personnalisé pour l'Email (Optionnel)</label>
                <textarea name="message_email" id="message_email" class="form-control" rows="3" placeholder="Ex: Bonjour, veuillez trouver ci-joint le rapport de notre intervention. N'hésitez pas à nous recontacter pour toute question."></textarea>
            </div>

            <div class="modal-footer" style="text-align: right;">
                <button type="button" class="btn btn-secondary" onclick="closeClotureModal()" style="margin-right: 10px;">Annuler</button>
                <button type="submit" class="btn" onclick="return confirm('Confirmez-vous la clôture de ce ticket et l\'envoi de l\'email ?');">
                    <i class="fa-solid fa-paper-plane"></i> Valider & Clôturer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    async function openClotureModal(interventionId) {
        const hiddenIdInput = document.getElementById('clotureInterventionId');
        const interventionText = document.getElementById('clotureInterventionIdText');
        const textarea = document.getElementById('rapport_dispatch');
        const modal = document.getElementById('clotureModal');

        if (!hiddenIdInput || !interventionText || !textarea || !modal) {
            console.error('Cloture modal elements introuvables. Vérifiez cloture_modal_ui.php sur la page.');
            return;
        }

        hiddenIdInput.value = interventionId;
        interventionText.textContent = interventionId;
        textarea.value = "Chargement des notes du technicien...";
        modal.style.display = 'block';

        try {
            const response = await fetch(`../api/get_intervention_details.php?id=${interventionId}`);
            const data = await response.json();
            if (data.success && data.rapport) {
                textarea.value = data.rapport;
            } else {
                textarea.value = "Aucune note technique trouvée. Rédigez le rapport manuellement.";
            }
        } catch (error) {
            textarea.value = "Erreur de chargement des notes. Rédigez le rapport manuellement.";
        }
    }

    function closeClotureModal() {
        const modal = document.getElementById('clotureModal');
        const hiddenIdInput = document.getElementById('clotureInterventionId');

        if (modal) {
            modal.style.display = 'none';
        }
        if (hiddenIdInput) {
            hiddenIdInput.value = '';
        }
    }

    async function rewriteClotureText(fieldId) {
        const textField = document.getElementById(fieldId);
        const originalText = textField.value.trim();

        if (!originalText || originalText.includes("Chargement des notes")) {
            alert("Veuillez d'abord taper un brouillon.");
            return;
        }

        const btn = event.currentTarget;
        const originalBtnText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ...';
        btn.disabled = true;

        try {
            const response = await fetch("../api/rewrite_cloture_text.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    text: originalText
                })
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                const errMsg = data.error || response.statusText;
                throw new Error("Erreur IA HTTP " + response.status + " : " + errMsg);
            }

            if (data.success && data.rewritten_text) {
                textField.value = data.rewritten_text.trim();
            } else {
                alert("Pas de réponse valide de l'IA.");
            }
        } catch (error) {
            console.error(error);
            alert("Erreur: " + error.message);
        } finally {
            btn.innerHTML = originalBtnText;
            btn.disabled = false;
        }
    }
</script>
