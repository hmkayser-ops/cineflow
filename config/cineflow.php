<?php

/*
|--------------------------------------------------------------------------
| Parâmetros de negócio do CineFlow
|--------------------------------------------------------------------------
| Valores usados no cálculo do horário de término das sessões e nas regras
| de preço. Podem ser ajustados pelo .env sem alterar o código.
*/

return [
    // Tempo reservado para trailers antes do filme (minutos)
    'minutos_trailers' => (int) env('CINEFLOW_MINUTOS_TRAILERS', 15),

    // Intervalo de limpeza da sala após a sessão (minutos)
    'minutos_limpeza' => (int) env('CINEFLOW_MINUTOS_LIMPEZA', 20),

    // Meia-entrada corresponde a 50% do valor da inteira (Lei 12.933/2013)
    'percentual_meia' => 0.5,

    // Máximo de dias para replicar uma sessão na programação em lote
    'max_dias_replicacao' => 13,
];
