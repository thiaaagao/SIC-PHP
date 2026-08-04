<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Politica de Privacidade - S.I.C.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/theme.css" rel="stylesheet">
    <link href="assets/toast.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand navbar-dark bg-secondary">
        <div class="container">
            <a href="index.php" class="navbar-brand fw-bold">S.I.C.</a>
            <div class="ms-auto d-flex gap-2 align-items-center">
                <a href="index.php" class="btn btn-outline-light btn-sm">Voltar</a>
                <button id="themeToggle" class="btn-theme-toggle" title="Alternar tema"></button>
            </div>
        </div>
    </nav>
    <div class="container my-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h2 class="mb-4">Politica de Privacidade</h2>

                <h5>1. Dados Coletados</h5>
                <p>O sistema de Problem Solving (P.S.) coleta os seguintes dados:</p>
                <ul>
                    <li><strong>Dados de identificacao:</strong> nome, usuario (para usuarios autenticados)</li>
                    <li><strong>Dados de conexao:</strong> endereco IP, hostname da estacao de trabalho</li>
                    <li><strong>Dados do chamado:</strong> subcategoria, descricao, setor, conf, prioridade, anexos</li>
                    <li><strong>Dados de navegacao:</strong> data/hora de abertura, resolucao, avaliacoes</li>
                </ul>

                <h5>2. Finalidade</h5>
                <p>Os dados sao utilizados exclusivamente para:</p>
                <ul>
                    <li>Registro e acompanhamento de problemas de TI</li>
                    <li>Resolucao de chamados de suporte tecnico</li>
                    <li>Analise de indicadores de performance (SLA)</li>
                    <li>Melhoria dos servicos de TI</li>
                </ul>

                <h5>3. Base Legal</h5>
                <p>O tratamento dos dados e realizado com base no interesse legitimo da empresa para gestao de servicos de TI e suporte tecnico (Art. 7, IX da LGPD).</p>

                <h5>4. Compartilhamento</h5>
                <p>Os dados podem ser compartilhados com:</p>
                <ul>
                    <li>Equipe de suporte TI</li>
                    <li>Sistema GLPI (para consulta de hostname, quando necessario)</li>
                    <li>Microsoft Teams (notificacoes de chamados, via Power Automate)</li>
                </ul>

                <h5>5. Retencao</h5>
                <p>Os dados sao mantidos pelo tempo necessario para cumprir as finalidades para as quais foram coletados, respeitando os prazos legais aplicaveis.</p>

                <h5>6. Seguranca</h5>
                <p>Medidas tecnicas e administrativas sao adotadas para proteger os dados contra acessos nao autorizados, perdas ou alteracoes, incluindo:</p>
                <ul>
                    <li>Senhas criptografadas (bcrypt)</li>
                    <li>Controle de acesso baseado em papeis</li>
                    <li>Registro de auditoria de acoes</li>
                    <li>Timeout de sessao automatico</li>
                </ul>

                <h5>7. Seus Direitos</h5>
                <p>Voce tem direito a:</p>
                <ul>
                    <li>Acessar os dados pessoais mantidos sobre voce</li>
                    <li>Solicitar correcao de dados incorretos</li>
                    <li>Solicitar a eliminacao dos seus dados (direito ao esquecimento)</li>
                    <li>Revogar o consentimento a qualquer momento</li>
                </ul>

                <h5>8. Contato</h5>
                <p>Para exercer seus direitos ou esclarecer duvidas, entre em contato com a equipe de TI.</p>

                <hr>
                <p class="text-muted small">Ultima atualizacao: <?= date('d/m/Y') ?></p>
            </div>
        </div>
    </div>
    <script src="assets/toast.js"></script>
    <script src="assets/theme.js"></script>
</body>
</html>
