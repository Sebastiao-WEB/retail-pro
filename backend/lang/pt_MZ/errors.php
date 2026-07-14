<?php

return [
    'page_title' => 'Erro :code | RetailPro POS',
    'badge' => 'Painel administrativo',
    'footer' => 'RetailPro POS · Área de administração',
    'go_back' => 'Voltar',
    'go_home' => 'Ir ao painel',
    'go_login' => 'Iniciar sessão',

    '4xx' => [
        'title' => 'Pedido inválido',
        'message' => 'Não foi possível concluir o pedido com os dados enviados.',
        'hint' => 'Verifique o endereço ou regresse ao painel administrativo.',
    ],
    '5xx' => [
        'title' => 'Erro no servidor',
        'message' => 'Ocorreu um problema ao processar o pedido.',
        'hint' => 'Tente novamente dentro de momentos.',
    ],

    '404' => [
        'title' => 'Página não encontrada',
        'message' => 'O endereço que procurou não existe ou foi movido.',
        'hint' => 'Verifique o URL ou regresse ao painel administrativo.',
    ],
    '403' => [
        'title' => 'Acesso negado',
        'message' => 'Não tem permissão para aceder a este recurso.',
        'hint' => 'Se acredita que isto é um erro, contacte o administrador do sistema.',
    ],
    '401' => [
        'title' => 'Não autenticado',
        'message' => 'Precisa de iniciar sessão para continuar.',
        'hint' => 'A sua sessão pode ter expirado.',
    ],
    '419' => [
        'title' => 'Sessão expirada',
        'message' => 'O formulário expirou por inactividade ou por segurança.',
        'hint' => 'Actualize a página e tente novamente.',
    ],
    '429' => [
        'title' => 'Demasiados pedidos',
        'message' => 'Foram feitos muitos pedidos em pouco tempo.',
        'hint' => 'Aguarde alguns segundos e tente novamente.',
    ],
    '500' => [
        'title' => 'Erro interno',
        'message' => 'Algo correu mal no servidor. A nossa equipa foi notificada.',
        'hint' => 'Tente novamente dentro de momentos.',
    ],
    '503' => [
        'title' => 'Serviço indisponível',
        'message' => 'O sistema está temporariamente em manutenção ou sobrecarregado.',
        'hint' => 'Volte a tentar dentro de alguns minutos.',
    ],

    'cashier_no_admin' => 'Utilizadores com perfil Caixa não podem aceder ao painel administrativo. Use a aplicação POS.',
];
