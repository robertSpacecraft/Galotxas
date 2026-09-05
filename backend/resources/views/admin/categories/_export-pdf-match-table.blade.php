<table class="matches">
    <colgroup>
        <col class="group">
        <col class="date">
        <col class="time">
        <col class="venue">
        <col class="participant">
        <col class="participant">
        <col class="result">
    </colgroup>
    <thead>
        <tr>
            <th>{{ $groupHeading }}</th>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Pista</th>
            <th>Participante A</th>
            <th>Participante B</th>
            <th>Resultado</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($matches as $match)
            <tr>
                <td>{{ $match->groupLabel }}</td>
                <td>{{ $match->date ?? '' }}</td>
                <td>{{ $match->time ?? '' }}</td>
                <td>{{ $match->venue ?? '' }}</td>
                <td>{{ $match->homeDisplayName }}</td>
                <td>{{ $match->awayDisplayName }}</td>
                <td class="result">{{ $match->resultText ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
