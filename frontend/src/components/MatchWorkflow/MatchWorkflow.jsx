import { useEffect, useState } from 'react';
import { useMatchWorkflow } from '../../hooks/useMatchWorkflow';
import styles from './MatchWorkflow.module.css';

const statusLabels = {
  scheduled: 'Programado',
  submitted: 'Pendiente de confirmación',
  validated: 'Validado',
  under_review: 'En revisión',
  postponed: 'Aplazado',
  cancelled: 'Cancelado',
};

const blockedReasonLabels = {
  match_closed: 'El partido ya está cerrado y no admite nuevos reportes.',
  under_review: 'El partido está en revisión por discrepancia de resultados.',
  already_reported_by_you: 'Ya has enviado tu resultado para este partido.',
  already_reported_by_teammate: 'Tu pareja ya ha enviado un resultado para este partido.',
};

const getEntryName = (entry) => {
  if (!entry) {
    return 'Por determinar';
  }

  if (entry.team) {
    return entry.team.name || `Equipo #${entry.team.id}`;
  }

  if (entry.player) {
    return entry.player.nickname
      || `${entry.player.name || ''} ${entry.player.lastname || ''}`.trim()
      || `Jugador #${entry.player.id}`;
  }

  return 'Participante';
};

const getStatusLabel = (status) => statusLabels[status] || status || 'Desconocido';

const getReportTitle = (report, match) => {
  if (!report?.side) {
    return 'Resultado reportado';
  }

  return report.side === 'home'
    ? getEntryName(match?.home_entry)
    : getEntryName(match?.away_entry);
};

const hasValidScores = (homeScore, awayScore) => {
  if (homeScore === '' || awayScore === '') {
    return false;
  }

  const parsedHome = Number(homeScore);
  const parsedAway = Number(awayScore);

  return Number.isInteger(parsedHome)
    && Number.isInteger(parsedAway)
    && parsedHome >= 0
    && parsedAway >= 0;
};

export const MatchWorkflow = ({ matchId, onMatchChange }) => {
  const {
    match,
    workflow,
    loading,
    actionLoading,
    error,
    message,
    fetchWorkflow,
    submitResult,
    confirmResult,
  } = useMatchWorkflow(matchId);

  const [formParams, setFormParams] = useState({
    home_score: '',
    away_score: '',
    comment: '',
  });
  const [formError, setFormError] = useState(null);

  useEffect(() => {
    fetchWorkflow();
  }, [fetchWorkflow]);

  useEffect(() => {
    if (match && onMatchChange) {
      onMatchChange(match);
    }
  }, [match, onMatchChange]);

  const handleChange = (event) => {
    const { name, value } = event.target;
    setFormParams((current) => ({ ...current, [name]: value }));
    setFormError(null);
  };

  const handleReportSubmit = async (event) => {
    event.preventDefault();

    if (!hasValidScores(formParams.home_score, formParams.away_score)) {
      setFormError('Indica tanteos válidos para ambos participantes.');
      return;
    }

    const success = await submitResult({
      home_score: Number(formParams.home_score),
      away_score: Number(formParams.away_score),
      comment: formParams.comment.trim() || null,
    });

    if (success) {
      setFormParams({ home_score: '', away_score: '', comment: '' });
    }
  };

  const handleConfirm = async () => {
    await confirmResult(formParams.comment.trim() || null);
  };

  if (loading) {
    return <div className={styles.stateMessage}>Cargando gestión del resultado...</div>;
  }

  if (!workflow) {
    return <div className={styles.stateMessage}>No se ha podido iniciar la gestión del resultado.</div>;
  }

  const matchStatus = workflow.match_status;
  const canConfirmOppositeReport = workflow.can_report && workflow.opposite_report;
  const blockedReason = blockedReasonLabels[workflow.blocked_reason] || null;
  const showBlockedReason = blockedReason
    && matchStatus !== 'validated'
    && matchStatus !== 'under_review';

  return (
    <section className={styles.container} aria-labelledby="match-workflow-title">
      <div className={styles.header}>
        <div>
          <h2 id="match-workflow-title" className={styles.title}>Gestión del resultado</h2>
          <p className={styles.subtitle}>
            Estado del flujo: <strong>{getStatusLabel(matchStatus)}</strong>
          </p>
        </div>
      </div>

      {error ? <div className={styles.error}>{error}</div> : null}
      {formError ? <div className={styles.error}>{formError}</div> : null}
      {message ? <div className={styles.success}>{message}</div> : null}

      {!workflow.participates ? (
        <div className={styles.notice}>
          Solo los participantes del partido pueden enviar o confirmar resultados desde esta pantalla.
        </div>
      ) : null}

      {matchStatus === 'under_review' ? (
        <div className={styles.noticeReview}>
          Hay una discrepancia entre reportes. El partido queda pendiente de resolución administrativa.
        </div>
      ) : null}

      {matchStatus === 'validated' ? (
        <div className={styles.noticeSuccess}>
          Resultado validado oficialmente.
        </div>
      ) : null}

      {workflow.my_report ? (
        <div className={styles.reportBox}>
          <h3 className={styles.reportTitle}>Tu reporte</h3>
          <p className={styles.reportScore}>
            {workflow.my_report.home_score} - {workflow.my_report.away_score}
          </p>
          <p className={styles.reportMeta}>
            Estado: {getStatusLabel(workflow.my_report.status)}
          </p>
        </div>
      ) : null}

      {workflow.same_side_report_by_teammate ? (
        <div className={styles.notice}>
          Tu lado ya ha enviado un reporte para este partido.
        </div>
      ) : null}

      {workflow.opposite_report ? (
        <div className={styles.reportBox}>
          <h3 className={styles.reportTitle}>
            Reporte de {getReportTitle(workflow.opposite_report, match)}
          </h3>
          <div className={styles.scoreComparison}>
            <div className={styles.scoreItem}>
              <span>{getEntryName(match?.home_entry)}</span>
              <strong>{workflow.opposite_report.home_score}</strong>
            </div>
            <div className={styles.scoreItem}>
              <span>{getEntryName(match?.away_entry)}</span>
              <strong>{workflow.opposite_report.away_score}</strong>
            </div>
          </div>
          {workflow.opposite_report.comment ? (
            <p className={styles.comment}>{workflow.opposite_report.comment}</p>
          ) : null}
          {canConfirmOppositeReport ? (
            <button
              type="button"
              className={styles.confirmButton}
              onClick={handleConfirm}
              disabled={actionLoading}
            >
              {actionLoading ? 'Confirmando...' : 'Confirmar este resultado'}
            </button>
          ) : null}
        </div>
      ) : null}

      {workflow.can_report ? (
        <form className={styles.form} onSubmit={handleReportSubmit}>
          <div className={styles.formIntro}>
            <h3 className={styles.formTitle}>
              {workflow.opposite_report ? 'Reportar una discrepancia' : 'Enviar resultado'}
            </h3>
            <p>
              El backend validará el tanteo oficial y decidirá si queda pendiente, confirmado o en revisión.
            </p>
          </div>

          <div className={styles.scoreFields}>
            <label className={styles.fieldGroup}>
              <span>{getEntryName(match?.home_entry)}</span>
              <input
                type="number"
                name="home_score"
                min="0"
                required
                className={styles.input}
                value={formParams.home_score}
                onChange={handleChange}
              />
            </label>

            <label className={styles.fieldGroup}>
              <span>{getEntryName(match?.away_entry)}</span>
              <input
                type="number"
                name="away_score"
                min="0"
                required
                className={styles.input}
                value={formParams.away_score}
                onChange={handleChange}
              />
            </label>
          </div>

          <label className={styles.fieldGroup}>
            <span>Comentario opcional</span>
            <textarea
              name="comment"
              className={styles.textarea}
              value={formParams.comment}
              onChange={handleChange}
              placeholder="Añade una observación si hace falta"
            />
          </label>

          <button
            type="submit"
            className={styles.submitButton}
            disabled={actionLoading}
          >
            {actionLoading ? 'Enviando...' : workflow.opposite_report ? 'Enviar discrepancia' : 'Enviar resultado'}
          </button>
        </form>
      ) : showBlockedReason ? (
        <div className={styles.notice}>{blockedReason}</div>
      ) : null}
    </section>
  );
};
