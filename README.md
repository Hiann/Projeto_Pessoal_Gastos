<div align="center">

# 📊 Finance Pro
### Gestão Financeira com Design Industrial e Alta Performance

![PHP Version](https://img.shields.io/badge/php-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Database](https://img.shields.io/badge/mysql-8.0-005C84?style=for-the-badge&logo=mysql&logoColor=white)
![Frontend](https://img.shields.io/badge/js-ES6-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![License](https://img.shields.io/badge/license-MIT-green?style=for-the-badge)

<p align="center">
  <a href="#-sobre-o-projeto">Sobre</a> •
  <a href="#-funcionalidades">Funcionalidades</a> •
  <a href="#-layout-e-ui">Layout</a> •
  <a href="#-como-executar">Instalação</a> •
  <a href="#-autor">Autor</a>
</p>

</div>

---

## 💡 Sobre o Projeto

O **Finance Pro** é uma solução fullstack para controle financeiro pessoal, projetada para fugir do "mais do mesmo". O objetivo foi criar uma aplicação que unisse a robustez do **PHP (PDO)** com uma experiência de usuário (UX) refinada, utilizando **JavaScript Moderno** sem dependência de frameworks pesados.

O diferencial deste projeto reside na sua interface **"Dark Industrial"**, onde cada componente — do calendário customizado aos gráficos interativos — foi desenhado para oferecer clareza de dados e facilidade de uso em qualquer dispositivo.

---

## 🌟 Funcionalidades Principais

### 🖥️ Dashboard Inteligente
- **Resumo em Tempo Real:** Cards dinâmicos com Entradas, Saídas, Saldo Atual e Contas Pendentes.
- **Visualização Gráfica:** Gráfico de rosca (Doughnut Chart) com distribuição percentual automática de gastos por categoria.
- **Filtros Temporais Sênior:** Sistema de filtragem por data com componente de calendário proprietário (baseado em Flatpickr, mas totalmente reestilizado).

### 📑 Gestão de Transações
- **CRUD Completo:** Adição, edição e remoção de receitas/despesas via Modal AJAX (sem recarregar a página desnecessariamente).
- **Categorização:** Sistema de etiquetas coloridas para fácil identificação visual.
- **Recorrência:** Suporte para lançamentos parcelados, fixos ou únicos.

### 📈 Relatórios Avançados
- **Análise de Evolução:** Gráfico de barras comparando Entradas vs. Saídas dia a dia.
- **Top Despesas:** Ranking visual das categorias que mais consomem o orçamento.
- **Exportação:** Ferramenta nativa para gerar relatórios em Excel ou visualização de impressão.

---

## 🎨 Layout e UI (Design System)

O projeto segue uma identidade visual estrita **"Dark Blue/Industrial"**, focada em contraste e legibilidade.

| **Componente** | **Detalhes Técnicos** |
|:---:|:---|
| **Calendário** | *Custom Build*. Abandonamos o visual nativo do navegador por um componente geométrico, responsivo e com suporte a seleção de meses (`shorthand`). |
| **Gráficos** | Implementados com **Chart.js**, customizados para seguir a paleta de cores do tema (Neon Green, Red, Blue). |
| **Inputs** | Estilo "Glassmorphism" sutil, sem fundos sólidos agressivos, priorizando a transparência e bordas suaves. |

---

## 🛠️ Tecnologias Utilizadas

Este projeto foi desenvolvido com as melhores práticas de desenvolvimento web clássico:

* **Back-end:** PHP 8+ (Orientado a Objetos e PDO para segurança contra SQL Injection).
* **Banco de Dados:** MySQL (Estrutura relacional normalizada).
* **Front-end:**
    * HTML5 Semântico & CSS3 (Grid Layout & Flexbox).
    * JavaScript ES6+ (Manipulação de DOM, Event Listeners, AJAX).
* **Bibliotecas (Libs):**
    * `Chart.js` (Visualização de Dados).
    * `Flatpickr` (Motor do Calendário).
    * `TomSelect` (Selects pesquisáveis e elegantes).
    * `FontAwesome` (Ícones vetoriais).

---

## 🚀 Como Executar

### Pré-requisitos
* Servidor Local (XAMPP, Laragon, Docker ou PHP Built-in Server).
* MySQL 5.7 ou superior.

### Passo a Passo

1.  **Clone o repositório**
    ```bash
    git clone [https://github.com/Hiann/finance-pro.git](https://github.com/Hiann/finance-pro.git)
    cd finance-pro
    ```

2.  **Configuração do Banco de Dados**
    * Crie um banco de dados chamado `finance_db`.
    * Importe o arquivo `sql/database.sql` (disponível na raiz do projeto) para criar as tabelas.

3.  **Conexão**
    * Abra o arquivo `includes/db.php`.
    * Ajuste as credenciais (`DB_HOST`, `DB_USER`, `DB_PASS`) conforme seu ambiente.

4.  **Iniciar**
    * Se estiver usando PHP puro:
        ```bash
        php -S localhost:8000
        ```
    * Acesse `http://localhost:8000` no seu navegador.

---

## 📂 Estrutura de Pastas

```text
finance-pro/
├── assets/          # CSS, JS e Imagens
├── includes/        # Conexão DB e Funções Globais (Helpers)
├── sql/             # Scripts de criação do banco
├── actions/         # Processamento de formulários (Salvar, Deletar)
├── index.php        # Dashboard Principal
├── relatorios.php   # Página de Análises
└── README.md        # Documentação

---

## 📫 Autor

<div align="center">

**Hiann Alexander Mendes de Oliveira** *Desenvolvedor Fullstack & Entusiasta de IA*

<a href="https://www.linkedin.com/in/hiann-alexander" target="_blank">
  <img src="https://img.shields.io/badge/LinkedIn-Conectar-0077B5?style=for-the-badge&logo=linkedin&logoColor=white" alt="LinkedIn Badge">
</a>
<a href="https://github.com/Hiann" target="_blank">
  <img src="https://img.shields.io/badge/GitHub-Ver_Perfil-100000?style=for-the-badge&logo=github&logoColor=white" alt="GitHub Badge">
</a>

</div>