import styles from './RankingTables.module.css';

const displayValue = (value) => (
  value === null || value === undefined || value === '' ? '—' : value
);

const displayDifference = (value) => {
  if (value === null || value === undefined || value === '') {
    return '—';
  }

  return Number(value) > 0 ? `+${value}` : value;
};

export const CategoryRankingTable = ({ ranking, categoryName, tableStyles = styles }) => (
  <div
    className={tableStyles.tableWrapper}
    role="region"
    aria-label={`Tabla de clasificación de ${categoryName}`}
    tabIndex="0"
  >
    <table className={tableStyles.table}>
      <caption className={tableStyles.visuallyHidden}>Clasificación de {categoryName}</caption>
      <thead>
        <tr className={tableStyles.headerRow}>
          <th scope="col">Pos.</th>
          <th scope="col">Participante</th>
          <th scope="col" className={tableStyles.center}><abbr title="Partidos jugados">PJ</abbr></th>
          <th scope="col" className={tableStyles.center}><abbr title="Victorias">V</abbr></th>
          <th scope="col" className={tableStyles.center}><abbr title="Derrotas">D</abbr></th>
          <th scope="col" className={tableStyles.center}><abbr title="Juegos a favor">JF</abbr></th>
          <th scope="col" className={tableStyles.center}><abbr title="Juegos en contra">JC</abbr></th>
          <th scope="col" className={tableStyles.center}><abbr title="Diferencia de juegos">Dif.</abbr></th>
          <th scope="col" className={tableStyles.center}>Puntos</th>
        </tr>
      </thead>
      <tbody>
        {ranking.map((row, index) => (
          <tr key={`${row.position ?? 'provisional'}-${index}`} className={tableStyles.row}>
            <td className={tableStyles.positionCell || tableStyles.pos}>
              <span className={tableStyles.posNum}>{displayValue(row.position)}</span>
            </td>
            <th scope="row" className={tableStyles.playerName || tableStyles.name}>
              {row.public_display_name || 'Participante'}
            </th>
            <td className={tableStyles.center}>{displayValue(row.played)}</td>
            <td className={tableStyles.center}>{displayValue(row.wins)}</td>
            <td className={tableStyles.center}>{displayValue(row.losses)}</td>
            <td className={tableStyles.center}>{displayValue(row.games_for)}</td>
            <td className={tableStyles.center}>{displayValue(row.games_against)}</td>
            <td className={tableStyles.center}>{displayDifference(row.games_diff)}</td>
            <td className={`${tableStyles.center} ${tableStyles.bold || tableStyles.points}`}>
              {displayValue(row.points)}
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  </div>
);
