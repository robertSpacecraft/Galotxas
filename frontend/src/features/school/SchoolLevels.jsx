import { getSchoolAgeRangeLabel, getSchoolDayLabel } from './schoolLabels';
import { SchoolLocation } from './SchoolLocation';
import styles from './SchoolPage.module.css';

const SchoolSchedule = ({ schedule }) => {
  const day = getSchoolDayLabel(schedule.day_of_week);

  return (
    <li className={styles.schedule}>
      <div className={styles.scheduleTime}>
        {day ? <strong>{day}</strong> : null}
        {schedule.starts_at && schedule.ends_at ? (
          <span>{schedule.starts_at}–{schedule.ends_at}</span>
        ) : null}
      </div>
      <SchoolLocation location={schedule.location} />
    </li>
  );
};

export const SchoolLevels = ({ levels }) => {
  if (levels.length === 0) {
    return (
      <p className={styles.localEmpty}>
        No hay niveles públicos disponibles en este momento.
      </p>
    );
  }

  return (
    <div className={styles.levelGrid}>
      {levels.map((level, index) => {
        const titleId = `school-level-${level.id ?? index}-title`;
        const ageRange = getSchoolAgeRangeLabel(level.minimum_age, level.maximum_age);

        return (
          <article
            key={level.id ?? `${level.name}-${index}`}
            className={styles.levelCard}
            aria-labelledby={titleId}
          >
            <h3 id={titleId}>{level.name ?? 'Nivel de Escuela'}</h3>
            {ageRange ? <p className={styles.ageRange}>{ageRange}</p> : null}
            {level.schedules.length > 0 ? (
              <>
                <h4>Horarios</h4>
                <ul className={styles.scheduleList}>
                  {level.schedules.map((schedule, scheduleIndex) => (
                    <SchoolSchedule
                      key={schedule.id ?? `${level.id}-${scheduleIndex}`}
                      schedule={schedule}
                    />
                  ))}
                </ul>
              </>
            ) : (
              <p className={styles.localEmpty}>Horario todavía no disponible.</p>
            )}
          </article>
        );
      })}
    </div>
  );
};
