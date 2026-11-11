function slots_do_dia(PDO $pdo, int $dentistaId, DateTimeImmutable $dia, string $prefixo): array
{
    $bloco  = config_valor($pdo, 'bloco_agenda_min', 30);
    $diaSem = (int)$dia->format('N');
    // BUGFIX (timezone): $dia chega em UTC (vem de marcar-calendario.php), por isso
    // os $chaves gerados abaixo também estão em UTC. Comparar com agora_bd() (DB TZ)
    // falha em horário de verão: slots até 1h no futuro pareciam "já passaram".
    // Convertemos agora_bd() para UTC para comparação coerente com $chave.
    $agora  = agora_bd($pdo)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $ini    = $dia->format('Y-m-d') . ' 00:00:00';
    $fim    = $dia->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

    $st = $pdo->prepare('SELECT hora_inicio, hora_fim FROM horarios_funcionamento WHERE dia_semana = ? ORDER BY hora_inicio');
    $st->execute([$diaSem]);
    $clinica = $st->fetchAll();
    if (!$clinica) {
        return [];
    }

    $st = $pdo->prepare('SELECT hora_inicio, hora_fim FROM disponibilidades_dentista WHERE dentista_id = ? AND dia_semana = ?');
    $st->execute([$dentistaId, $diaSem]);
    $dentista = $st->fetchAll();

    if (!$dentista) {
        $dentista = $clinica;
    }

    $st = $pdo->prepare(
        'SELECT inicio, fim FROM bloqueios_horario
          WHERE (dentista_id IS NULL OR dentista_id = ?) AND inicio < ? AND fim > ?'
    );
    $st->execute([$dentistaId, $fim, $ini]);
    $bloqueios = $st->fetchAll();

    $st = $pdo->prepare(
        'SELECT inicio_bloco, consulta_id, token_reserva FROM horarios_ocupados
          WHERE dentista_id = ? AND inicio_bloco >= ? AND inicio_bloco < ?
            AND (consulta_id IS NOT NULL OR expira_em > NOW())'
    );
    $st->execute([$dentistaId, $ini, $fim]);
    $ocupados = [];
    foreach ($st as $o) {
        $ocupados[$o['inicio_bloco']] = $o;
    }

    $dentro = function (array $blocos, int $a, int $b): bool {
        foreach ($blocos as $bl) {
            if ($a >= tempo_em_minutos($bl['hora_inicio']) && $b <= tempo_em_minutos($bl['hora_fim'])) {
                return true;
            }
        }
        return false;
    };

    $minIni = min(array_map(fn($b) => tempo_em_minutos($b['hora_inicio']), $clinica));
    $maxFim = max(array_map(fn($b) => tempo_em_minutos($b['hora_fim']), $clinica));

    $slots = [];
    for ($m = $minIni; $m + $bloco <= $maxFim; $m += $bloco) {
        $inicioDT = $dia->setTime(intdiv($m, 60), $m % 60);
        $chave    = $inicioDT->format('Y-m-d H:i:00');
        $fimSlot  = $inicioDT->modify('+' . $bloco . ' minutes')->format('Y-m-d H:i:00');
        $token    = null;

        if ($chave <= $agora || !$dentro($clinica, $m, $m + $bloco) || !$dentro($dentista, $m, $m + $bloco)) {
            $estado = 'indisponivel';
        } else {
            $estado = 'livre';
            foreach ($bloqueios as $b) {
                if ($chave < $b['fim'] && $fimSlot > $b['inicio']) { $estado = 'indisponivel'; break; }
            }
            if ($estado === 'livre' && isset($ocupados[$chave])) {
                $o = $ocupados[$chave];
                if ($o['consulta_id'] !== null) {
                    $estado = 'ocupado';
                } elseif (str_starts_with((string)$o['token_reserva'], $prefixo . ':')) {
                    $estado = 'reservado_meu';
                    $token  = $o['token_reserva'];
                } else {
                    $estado = 'reservado_outro';
                }
            }
        }

        $slots[$chave] = ['inicio' => $chave, 'hora' => $inicioDT->format('H:i'), 'estado' => $estado, 'token' => $token];
    }
    return $slots;
}