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
            font-size: {{ $preset['title_font_pt'] }}pt;
            line-height: 1.05;
            text-align: center;
        }

        .meta {
            width: 100%;
            margin-bottom: {{ $preset['gap_mm'] }}mm;
            border-collapse: collapse;
            font-size: {{ $preset['meta_font_pt'] }}pt;
            line-height: 1.05;
        }

        .meta td {
            padding: 0.25mm 0.7mm;
            vertical-align: top;
        }

        .participants-title,
        .section-title {
            margin: {{ $preset['gap_mm'] }}mm 0 0.5mm;
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
            font-weight: 700;
            text-align: center;
        }

        .matches .group {
            width: 9%;
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
            width: 16%;
            text-align: center;
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
