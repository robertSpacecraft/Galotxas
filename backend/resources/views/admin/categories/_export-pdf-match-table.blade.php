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
        @php
            $previousGroupLabel = null;
        @endphp
        @foreach ($matches as $match)
            @php
                $isGroupStart = $previousGroupLabel !== $match->groupLabel;
            @endphp
            <tr class="{{ $isGroupStart ? 'group-start' : 'group-continuation' }}">
                <td class="group-label">{{ $isGroupStart ? $match->groupLabel : '' }}</td>
                <td>{{ $match->date ?? '' }}</td>
                <td>{{ $match->time ?? '' }}</td>
                <td>{{ $match->venue ?? '' }}</td>
                <td>{{ $match->homeDisplayName }}</td>
                <td>{{ $match->awayDisplayName }}</td>
                @if ($match->resultText === null)
                    <td class="result result-empty"></td>
                @else
                    <td class="result">{{ $match->resultText }}</td>
                @endif
            </tr>
            @php
                $previousGroupLabel = $match->groupLabel;
            @endphp
        @endforeach
    </tbody>
</table>
