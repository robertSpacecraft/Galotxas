<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $document->championshipName }} - {{ $document->categoryName }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: {{ $preset['margin_mm'] }}mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #000;
            background: #fff;
            font-family: "DejaVu Sans", sans-serif;
        }

        h1 {
            margin: 0 0 {{ $preset['gap_mm'] }}mm;
            padding-bottom: 0.7mm;
            border-bottom: 0.5mm solid #222;
            font-size: {{ $preset['title_font_pt'] }}pt;
            line-height: 1.05;
            text-align: center;
        }

        .meta {
            width: 100%;
            margin-bottom: {{ $preset['gap_mm'] }}mm;
            border-collapse: collapse;
            border: 0.2mm solid #555;
            background: #f2f2f2;
            font-size: {{ $preset['meta_font_pt'] }}pt;
            line-height: 1.05;
        }

        .meta td {
            padding: 0.25mm 0.7mm;
            border-right: 0.15mm solid #888;
            vertical-align: top;
        }

        .participants-title,
        .section-title {
            margin: {{ $preset['gap_mm'] }}mm 0 0.5mm;
            padding: 0.35mm 0.7mm;
            border-top: 0.35mm solid #222;
            border-bottom: 0.15mm solid #666;
            background: #dedede;
            font-size: {{ $preset['section_font_pt'] }}pt;
            line-height: 1;
        }

        .participants {
            width: 100%;
            margin-bottom: {{ $preset['gap_mm'] }}mm;
            border-collapse: collapse;
            font-size: {{ $preset['participant_font_pt'] }}pt;
            line-height: {{ $preset['line_height'] }};
            table-layout: fixed;
        }

        .participants td {
            width: 50%;
            padding: {{ $preset['cell_padding_mm'] / 2 }}mm 1mm;
            border: 0.15mm solid #555;
            vertical-align: top;
            word-wrap: break-word;
        }

        .participants tr,
        .matches tr {
            page-break-inside: avoid;
        }

        .matches {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: {{ $preset['table_font_pt'] }}pt;
            line-height: {{ $preset['line_height'] }};
        }

        .matches th,
        .matches td {
            padding: {{ $preset['cell_padding_mm'] }}mm 0.55mm;
            border: 0.15mm solid #000;
            vertical-align: middle;
            word-wrap: break-word;
        }

        .matches th {
            border-top-width: 0.35mm;
            border-bottom-width: 0.3mm;
            background: #d7d7d7;
            font-weight: 700;
            text-align: center;
        }

        .matches tbody tr.group-start td {
            border-top: 0.45mm solid #111;
        }

        .matches .group-label {
            background: #eeeeee;
            font-weight: 700;
            text-align: center;
        }

        .matches .group {
            width: 11%;
        }

        .matches .date {
            width: 10%;
        }

        .matches .time {
            width: 7%;
        }

        .matches .venue {
            width: 13%;
        }

        .matches .participant {
            width: 22.5%;
        }

        .matches .result {
            width: 14%;
            border-right-width: 0.3mm;
            border-left-width: 0.3mm;
            text-align: center;
        }

        .matches td.result-empty {
            background: #fff;
        }
    </style>
</head>
<body>
    <h1>{{ $document->championshipName }} - {{ $document->categoryName }}</h1>

    <table class="meta">
        <tr>
            <td><strong>Temporada:</strong> {{ $document->seasonName ?? '' }}</td>
            <td><strong>Modalidad:</strong> {{ $document->modalityLabel }}</td>
            <td><strong>Participantes:</strong> {{ $document->participantCount }}</td>
        </tr>
    </table>

    <h2 class="participants-title">Participantes</h2>
    @php
        $participantRows = array_chunk($document->participants, 2);
    @endphp
    <table class="participants">
        @foreach ($participantRows as $participantRow)
            <tr>
                <td>{{ $participantRow[0] }}</td>
                <td>{{ $participantRow[1] ?? '' }}</td>
            </tr>
        @endforeach
    </table>

    @if ($document->leagueMatches !== [])
        <h2 class="section-title">Liga</h2>
        @include('admin.categories._export-pdf-match-table', [
            'matches' => $document->leagueMatches,
            'groupHeading' => 'Jornada',
        ])
    @endif

    @if ($document->cupMatches !== [])
        <h2 class="section-title">Copa</h2>
        @include('admin.categories._export-pdf-match-table', [
            'matches' => $document->cupMatches,
            'groupHeading' => 'Fase',
        ])
    @endif
</body>
</html>
