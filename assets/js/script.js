let tomSelectInstance;

document.addEventListener("DOMContentLoaded", function() {
    console.log("Sistema Carregado (V3.1)");

    // 1. TOM SELECT
    try {
        const selectElement = document.getElementById('inputCategoria');
        if(selectElement) {
            tomSelectInstance = new TomSelect(selectElement, {
                create: false,
                sortField: { field: "text", direction: "asc" },
                placeholder: "Buscar categoria...",
                dropdownParent: 'body',
                wrapperClass: 'ts-wrapper ts-clean', 
                controlClass: 'ts-control',
                render: { option: function(data, escape) { return '<div class="option">' + escape(data.text) + '</div>'; } },
                onInitialize: function() { this.control.style.boxShadow = "inset 0 2px 4px rgba(0,0,0,0.3)"; }
            });
        }
    } catch (e) { console.warn("TomSelect erro:", e); }

    // 2. FLATPICKR
    try {
        if(document.getElementById('datePicker')) {
            const fp = flatpickr("#datePicker", {
                plugins: [new monthSelectPlugin({ shorthand: false, dateFormat: "Y-m-d", altFormat: "F Y", theme: "dark" })],
                locale: "pt", disableMobile: true, altInput: true, altFormat: "F \\d\\e Y",
                onChange: function(selectedDates) {
                    if (selectedDates.length > 0) {
                        const date = selectedDates[0];
                        const inicio = `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-01`;
                        const fim = `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${new Date(date.getFullYear(), date.getMonth()+1, 0).getDate()}`;
                        const path = window.location.pathname;
                        const page = path.split("/").pop() || 'index.php';
                        window.location.href = `${page}?data_inicio=${inicio}&data_fim=${fim}`;
                    }
                }
            });
            const trigger = document.getElementById('pickerTrigger');
            if (trigger) { trigger.addEventListener('click', function() { fp.open(); }); }
        }
    } catch (e) { console.warn("Flatpickr erro:", e); }

    // 3. CHART.JS
    try {
        const ctxElement = document.getElementById('myChart');
        if (ctxElement && typeof chartDataPHP !== 'undefined' && chartDataPHP.length > 0) {
            const ctx = ctxElement.getContext('2d');
            const labels = chartDataPHP.map(item => item.nome);
            const data = chartDataPHP.map(item => item.total);
            const baseColors = chartDataPHP.map(item => item.cor_hex);
            new Chart(ctx, {
                type: 'doughnut',
                data: { labels: labels, datasets: [{ data: data, backgroundColor: baseColors, borderWidth: 0, hoverOffset: 15, borderRadius: 5 }] },
                options: { responsive: true, cutout: '75%', plugins: { legend: { position: 'right', labels: { color: '#9ca3af', usePointStyle: true, boxWidth: 8, font: { family: 'Poppins', size: 12 } } } }, layout: { padding: 20 } }
            });
        }
    } catch (e) { console.warn("ChartJS erro:", e); }

    // 4. LÓGICA DE RECORRÊNCIA (SHOW/HIDE PARCELAS)
    const selectRepeticao = document.getElementById('inputRepeticao');
    const inputParcelas = document.getElementById('inputParcelas');
    
    if(selectRepeticao && inputParcelas) {
        selectRepeticao.addEventListener('change', function() {
            if(this.value === 'unica') {
                inputParcelas.style.display = 'none';
                inputParcelas.required = false;
            } else {
                inputParcelas.style.display = 'block';
                inputParcelas.required = true;
                setTimeout(() => inputParcelas.focus(), 100);
            }
        });
    }
});

// --- FUNÇÕES GLOBAIS (ONCLICK) ---

window.openModal = function() {
    const modal = document.getElementById('transactionModal');
    if(!modal) return;
    
    // Reset Form
    document.getElementById('formTransacao').reset();
    document.getElementById('inputId').value = '';
    
    const title = document.getElementById('modalTitle');
    if(title) title.innerText = 'Nova Transação';
    
    document.getElementById('inputData').value = new Date().toISOString().split('T')[0];
    
    // Reset Recorrência
    const selRep = document.getElementById('inputRepeticao');
    const inpPar = document.getElementById('inputParcelas');
    if(selRep) selRep.value = 'unica';
    if(inpPar) inpPar.style.display = 'none';

    // Reset Tema
    const rdDes = document.getElementById('typeDespesa');
    if(rdDes) { rdDes.checked = true; window.updateModalTheme(); }

    if(tomSelectInstance) tomSelectInstance.clear();
    
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('active'), 10);
}

window.closeModal = function() {
    const modal = document.getElementById('transactionModal');
    if(modal) {
        modal.classList.remove('active');
        setTimeout(() => modal.style.display = 'none', 300);
    }
}

window.onclick = function(event) {
    const modal = document.getElementById('transactionModal');
    if (modal && event.target == modal) window.closeModal();
}

window.updateModalTheme = function() {
    const isReceita = document.getElementById('typeReceita').checked;
    const btnSave = document.getElementById('btnSave');
    const inputValor = document.getElementById('inputValor');
    
    if(btnSave && inputValor) {
        btnSave.classList.remove('income-mode', 'expense-mode');
        inputValor.style.borderBottomColor = ''; 

        if(isReceita) {
            btnSave.classList.add('income-mode');
            btnSave.innerText = 'Receber Valor';
            inputValor.style.borderBottomColor = '#10b981'; 
        } else {
            btnSave.classList.add('expense-mode');
            btnSave.innerText = 'Pagar Valor';
            inputValor.style.borderBottomColor = '#ef4444'; 
        }
    }
}

window.editarTransacao = function(dados) {
    // Campos básicos
    document.getElementById('inputId').value = dados.id;
    document.getElementById('inputDesc').value = dados.descricao;
    document.getElementById('inputValor').value = dados.valor;
    document.getElementById('inputData').value = dados.data_transacao;
    
    // Tipo e Tema
    if(dados.tipo === 'receita') {
        document.getElementById('typeReceita').checked = true;
    } else {
        document.getElementById('typeDespesa').checked = true;
    }
    window.updateModalTheme();
    
    // Categoria
    if(tomSelectInstance) { 
        tomSelectInstance.setValue(dados.categoria_id); 
    } else { 
        const cat = document.getElementById('inputCategoria');
        if(cat) cat.value = dados.categoria_id;
    }
    
    // Status
    document.getElementById('inputStatus').checked = (dados.status === 'pago');
    
    // Ao editar, escondemos a recorrência para evitar conflito (edição simples)
    // Opcional: Você pode querer permitir editar, mas é complexo logicamente
    const selRep = document.getElementById('inputRepeticao');
    const inpPar = document.getElementById('inputParcelas');
    if(selRep) selRep.value = 'unica'; // Força 'unica' na edição visual
    if(inpPar) inpPar.style.display = 'none';

    // Título
    const title = document.getElementById('modalTitle');
    if(title) title.innerText = 'Editar Transação';
    
    const modal = document.getElementById('transactionModal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('active'), 10);
}

window.toggleSidebar = function() {
    document.getElementById('mySidebar').classList.toggle('active');
    document.getElementById('myOverlay').classList.toggle('active');
}

window.closeSidebar = function() {
    document.getElementById('mySidebar').classList.remove('active');
    document.getElementById('myOverlay').classList.remove('active');
}

window.showToast = function(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if(!container) return;
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = type === 'success' ? `<i class="fas fa-check-circle"></i> ${message}` : `<i class="fas fa-times-circle"></i> ${message}`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; setTimeout(() => toast.remove(), 300); }, 3000);
}

window.formatarMoedaJS = function(valor) { return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valor); }

// --- FUNÇÃO ATUALIZADA: TOGGLE STATUS (COM MODO DEBUG ATIVADO) ---
window.toggleStatus = async function(id, element) {
    try {
        const originalText = element.innerText;
        element.innerText = '...'; 
        element.style.opacity = '0.7';
        
        // 1. Pega as datas da URL atual (para manter a consistência dos cálculos)
        const urlParams = new URLSearchParams(window.location.search);
        
        // Se não tiver data na URL, calcula o mês atual padrão YYYY-MM-01 e YYYY-MM-Last
        const hoje = new Date();
        const inicioPadrao = new Date(hoje.getFullYear(), hoje.getMonth(), 1).toISOString().split('T')[0];
        const fimPadrao = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0).toISOString().split('T')[0];

        const dataInicio = urlParams.get('data_inicio') || inicioPadrao;
        const dataFim = urlParams.get('data_fim') || fimPadrao;

        // 2. Envia ID + Datas para o backend
        const response = await fetch('actions/toggle_status.php', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify({ 
                id: id,
                data_inicio: dataInicio,
                data_fim: dataFim
            }) 
        });

        const result = await response.json();
        
        if (result.success) {
            // Atualiza o botão
            element.className = `status-badge ${result.novo_status}`;
            element.innerText = result.novo_status === 'pago' ? 'PAGO' : 'PENDENTE';
            element.style.opacity = '1';
            
            // --- CÓDIGO PARA ANIMAR O AVISO DE ATRASO ---
            // Procura a linha (<tr>) atual onde o botão foi clicado
            let linhaTabela = element.closest('tr');
            if (linhaTabela) {
                // Procura o aviso de atraso dentro dessa mesma linha
                let avisoAtraso = linhaTabela.querySelector('.alerta-atraso');
                
                if (avisoAtraso) {
                    // Se o novo status for pago, esconde o aviso. Se for pendente, mostra.
                    if (result.novo_status === 'pago') {
                        avisoAtraso.style.display = 'none';
                    } else {
                        avisoAtraso.style.display = 'inline-flex';
                    }
                }
            }
            // --------------------------------------------

            // Atualiza os Cards do Topo com os valores corretos
            const elEntradas = document.getElementById('total-entradas');
            if(elEntradas) {
                const fmt = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
                elEntradas.innerText = fmt.format(result.totais.entradas);
                document.getElementById('total-saidas').innerText = fmt.format(result.totais.saidas);
                
                // Trata cor do saldo (positivo/negativo)
                const elSaldo = document.getElementById('total-saldo');
                elSaldo.innerText = fmt.format(result.totais.saldo);
                elSaldo.style.color = result.totais.saldo >= 0 ? '#10b981' : '#ef4444'; // Verde ou Vermelho

                document.getElementById('total-pendente').innerText = fmt.format(result.totais.pendente);
            }
            
            if(typeof window.showToast === 'function') {
                window.showToast('Status atualizado!', 'success');
            }
        } else { 
            // SE O PHP RECUSAR, MOSTRA O MOTIVO EXATO:
            alert('O PHP Recusou. Motivo: ' + result.message); 
            element.innerText = originalText; 
            element.style.opacity = '1';
        }
    } catch (error) { 
        // SE DER ERRO FATAL (Ex: PHP quebrou e não gerou JSON)
        alert('Erro Fatal de Comunicação! Pressione F12 e olhe a aba Network (Rede).');
        console.error('Erro Técnico:', error); 
        element.innerText = originalText; 
        element.style.opacity = '1';
    }
}

window.confirmarExclusao = function(id) {
    if (confirm('Tem certeza que deseja excluir esta transação permanentemente?')) {
        // 1. Pega os parâmetros atuais da URL (Onde você está agora)
        const params = new URLSearchParams(window.location.search);
        const dataInicio = params.get('data_inicio');
        const dataFim = params.get('data_fim');

        // 2. Monta o link de exclusão base
        let urlDeletar = `actions/delete.php?id=${id}`;

        // 3. Se houver datas na URL, anexa elas no link de deletar
        if (dataInicio && dataFim) {
            urlDeletar += `&data_inicio=${dataInicio}&data_fim=${dataFim}`;
        }

        // 4. Redireciona
        window.location.href = urlDeletar;
    }
}