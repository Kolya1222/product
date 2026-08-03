<?php

namespace roilafx\Product;

use Illuminate\Support\Facades\Event;

Event::listen('evolution.OnDocFormRender', function ($params) {
    $docId = $params['id'] ?? 0;
    if (!$docId) return;

    $moduleId = md5('Пресеты атрибутов');

    $html = '<div class="tab-page" id="tabVariants">';
    $html .= '<h2 class="tab"><i class="fa fa-cubes"></i> Вариации</h2>';
    $html .= '<script>tpSettings.addTabPage(document.getElementById("tabVariants"));</script>';
    $html .= '<div class="sectionBody">';
    $html .= view('products::tab', [
        'productId' => $docId,
        'moduleId'  => $moduleId
    ])->render();
    $html .= '</div></div>';
    evo()->regClientHTMLBlock($html);
});
