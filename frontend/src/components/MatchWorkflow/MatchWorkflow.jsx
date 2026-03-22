import React, { useEffect, useState } from 'react';
import { useMatchWorkflow } from '../../hooks/useMatchWorkflow';
import styles from './MatchWorkflow.module.css';

export const MatchWorkflow = ({ matchId }) => {
  const { 
    workflow, loading, actionLoading, error, fetchWorkflow, submitResult, confirmResult 
  } = useMatchWorkflow(matchId);

  const [formParams, setFormParams] = useState({
    home_score: '',
    away_score: '',
    comment: ''
  });

  useEffect(() => {
    fetchWorkflow();
  }, [fetchWorkflow]);

  if (loading) return <div className={styles.loader}>Cargando flujo del partido...</div>;
  if (!workflow) return <div className={styles.loader}>Iniciando flujo...</div>;

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormParams(prev => ({ ...prev, [name]: value }));
  };

  const handleReportSubmit = async (e) => {
    e.preventDefault();
    await submitResult({
      home_score: parseInt(formParams.home_score, 10),
      away_score: parseInt(formParams.away_score, 10),
      comment: formParams.comment
    });
  };

  const handleConfirm = async () => {
    await confirmResult();
  };

  return (
    <div className={styles.container}>
      <h3 className={styles.title}>Gestión de Partido</h3>
      
      {error && <div className={styles.error}>{error}</div>}

      {/* If match is under review */}
      {workflow.status === 'under_review' && (
        <div className={styles.noticeReview}>
          <p>⚠️ Este partido está <strong>en revisión por conflicto</strong>. Un administrador resolverá el resultado final pronto.</p>
        </div>
      )}

      {/* If user needs to confirm opposite report */}
      {workflow.opposite_report && workflow.status !== 'under_review' && (
        <div className={styles.rivalReport}>
          <h4 className={styles.rivalTitle}>El rival ha reportado este resultado:</h4>
          
          <div className={styles.scoreComparison}>
            <div className={styles.teamScore}>
              <span className={styles.teamName}>Local</span>
              <span className={styles.scoreValue}>{workflow.opposite_report.home_score}</span>
            </div>
            <div className={styles.teamScore}>
              <span className={styles.teamName}>Visitante</span>
              <span className={styles.scoreValue}>{workflow.opposite_report.away_score}</span>
            </div>
          </div>

          {workflow.opposite_report.comment && (
            <div className={styles.commentBox}>
              <strong>Comentario del rival:</strong> {workflow.opposite_report.comment}
            </div>
          )}

          <button 
            className={`${styles.button} ${styles.confirmButton}`} 
            onClick={handleConfirm}
            disabled={actionLoading}
            style={{marginTop: '1.5rem'}}
          >
            {actionLoading ? 'Confirmando...' : 'Confirmar Resultado'}
          </button>
        </div>
      )}

      {/* If user can report result initially or if they need to dispute what the rival said */}
      {workflow.can_report && workflow.status !== 'under_review' && (
        <form onSubmit={handleReportSubmit}>
          {workflow.opposite_report ? (
             <p style={{marginBottom: '1rem', fontSize: '0.875rem', color: '#4b5563'}}>
               <em>¿No estás de acuerdo? Puedes reportar tu versión creando un conflicto.</em>
             </p>
          ) : null}
          
          <div className={styles.formGroup}>
            <label className={styles.label}>Puntuación Local</label>
            <input 
              type="number" 
              name="home_score"
              min="0"
              required
              className={styles.input} 
              value={formParams.home_score} 
              onChange={handleChange} 
            />
          </div>

          <div className={styles.formGroup}>
            <label className={styles.label}>Puntuación Visitante</label>
            <input 
              type="number" 
              name="away_score"
              min="0"
              required
              className={styles.input} 
              value={formParams.away_score} 
              onChange={handleChange} 
            />
          </div>

          <div className={styles.formGroup}>
            <label className={styles.label}>Comentario (Opcional)</label>
            <textarea 
              name="comment"
              className={styles.textarea} 
              value={formParams.comment} 
              onChange={handleChange} 
              placeholder="Escribe alguna anotación sobre el partido..."
            />
          </div>

          <button 
            type="submit" 
            className={styles.button}
            disabled={actionLoading}
          >
            {actionLoading ? 'Enviando...' : 'Enviar Resultado'}
          </button>
        </form>
      )}

      {!workflow.can_report && !workflow.opposite_report && workflow.status === 'scheduled' && (
         <div style={{color: '#6b7280', textAlign: 'center'}}>
           Aún no puedes reportar el resultado para este partido.
         </div>
      )}
      
      {workflow.status === 'validated' && (
         <div style={{color: '#10b981', textAlign: 'center', fontWeight: 'bold', fontSize: '1.125rem'}}>
           ✅ Resultado Validado
         </div>
      )}
    </div>
  );
};
