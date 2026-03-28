import React from 'react';
import styles from './TournamentFilters.module.css';

export const TournamentFilters = ({ seasons, currentFilters, onFilterChange }) => {
  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    
    if (type === 'checkbox') {
      const newFilters = { ...currentFilters };
      if (checked) {
        newFilters[name] = '1';
      } else {
        delete newFilters[name];
      }
      onFilterChange(newFilters);
    } else {
      onFilterChange({
        ...currentFilters,
        [name]: value
      });
    }
  };

  return (
    <div className={styles.filtersContainer}>
      <div className={styles.filterGroup}>
        <label htmlFor="season_id">Temporada</label>
        <select 
          id="season_id" 
          name="season_id" 
          value={currentFilters.season_id || ''} 
          onChange={handleChange}
        >
          <option value="">Todas</option>
          {seasons.map(season => (
            <option key={season.id} value={season.id}>{season.name}</option>
          ))}
        </select>
      </div>

      <div className={styles.filterGroup}>
        <label htmlFor="type">Tipo</label>
        <select 
          id="type" 
          name="type" 
          value={currentFilters.type || ''} 
          onChange={handleChange}
        >
          <option value="">Todos</option>
          <option value="singles">Individual</option>
          <option value="doubles">Parejas</option>
        </select>
      </div>

      <div className={styles.filterGroup}>
        <label htmlFor="status">Estado</label>
        <select 
          id="status" 
          name="status" 
          value={currentFilters.status || ''} 
          onChange={handleChange}
        >
          <option value="">Cualquiera</option>
          <option value="active">En curso</option>
          <option value="finished">Finalizado</option>
        </select>
      </div>

      <div className={styles.filterCheck}>
        <label>
          <input 
            type="checkbox" 
            name="registration_open" 
            checked={currentFilters.registration_open === '1'} 
            onChange={handleChange}
          />
          Inscripción abierta
        </label>
      </div>
    </div>
  );
};
