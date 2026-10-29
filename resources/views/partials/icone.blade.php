@php
$icones = [
  'logo' => '<path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v11a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 17.5z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M10 9.2v5.6l4.6-2.8z" fill="currentColor"/>',
  'painel' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
  'sessoes' => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/>',
  'filmes' => '<rect x="3.5" y="4" width="17" height="16" rx="2"/><path d="M7.5 4v16M16.5 4v16M3.5 9h4M3.5 15h4M16.5 9h4M16.5 15h4"/>',
  'salas' => '<path d="M5 19v-6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v6M5 15h14M7 11V7a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v4"/>',
  'usuarios' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.6 3.4-5.5 6.5-5.5s5.7 1.9 6.5 5.5M16 4.8a3.4 3.4 0 0 1 0 6.4M18 14.8c1.7.7 3 2.4 3.5 5.2"/>',
  'auditoria' => '<path d="M12 3.5l7.5 3v5.3c0 4.4-3.1 7.6-7.5 8.7-4.4-1.1-7.5-4.3-7.5-8.7V6.5z"/><path d="M9 12l2.2 2.2L15.5 10"/>',
  'mais' => '<path d="M12 5v14M5 12h14"/>',
  'editar' => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
  'planta' => '<rect x="4" y="5" width="4" height="4" rx="1"/><rect x="10" y="5" width="4" height="4" rx="1"/><rect x="16" y="5" width="4" height="4" rx="1"/><rect x="4" y="11" width="4" height="4" rx="1"/><rect x="10" y="11" width="4" height="4" rx="1"/><rect x="16" y="11" width="4" height="4" rx="1"/><path d="M4 19h16"/>',
  'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
  'voltar' => '<path d="M15 5l-7 7 7 7"/>',
  'avancar' => '<path d="M9 5l7 7-7 7"/>',
];
@endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icones[$n] ?? '' !!}</svg>
