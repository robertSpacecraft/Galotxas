import React from 'react';
import styles from './Nosotros.module.css';
import nosotrosHero from '../../assets/nosotros_hero.png';
import pelotaTrapo from '../../assets/pelota_trapo.png';
import escuelaGrupo from '../../assets/escuela_grupo.png';
import igualdadJugadora from '../../assets/igualdad_jugadora.png';
import convivenciaGatxamiga from '../../assets/convivencia_gatxamiga.png';

export const Nosotros = () => {
  return (
    <div className={styles.container}>
      {/* 1. Cabecera Emocional */}
      <section className={styles.heroSection}>
        <div className={styles.heroImageWrapper}>
          <img src={nosotrosHero} alt="Galotxeta de Monóvar" className={styles.heroImage} />
          <div className={styles.heroOverlay}></div>
        </div>
        <div className={styles.heroContent}>
          <h1 className={styles.heroTitle}>Mucho más que un juego: la tradición viva de Monóvar.</h1>
        </div>
      </section>

      <div className={styles.contentWrapper}>
        {/* 2. ¿Quiénes somos? */}
        <section className={styles.section}>
          <div className={styles.textBlock}>
            <h2 className={styles.sectionTitle}>¿Quiénes somos?</h2>
            <p className={styles.paragraph}>
              Somos el nexo de unión entre el pasado y el futuro de nuestro pueblo. En el Club de Galotxetes de Monóvar, nuestra misión es clara: promover, proteger y dignificar la práctica de las galotxes (o galotxetes), una modalidad de pelota valenciana única en el mundo que define nuestra identidad.
            </p>
            <p className={styles.paragraph}>
              Nuestra visión es convertir la Galotxeta en un espacio de encuentro intergeneracional, asegurando que el sonido de la pelota de trapo siga resonando en Monóvar durante los próximos siglos, conservando un deporte tradicional que no se encuentra en ningún otro lugar.
            </p>
          </div>
        </section>

        {/* 3. Nuestros Valores */}
        <section className={`${styles.section} ${styles.reverseSection}`}>
          <div className={styles.imageBlock}>
            <img src={pelotaTrapo} alt="Pelota de trapo artesanal" className={styles.sectionImage} />
          </div>
          <div className={styles.textBlock}>
            <h2 className={styles.sectionTitle}>Nuestros Valores</h2>
            <ul className={styles.valuesList}>
              <li><strong>Conservación:</strong> Orgullo por lo nuestro. Mantenemos viva una joya del Vinalopó.</li>
              <li><strong>Compañerismo:</strong> En la Galotxeta no hay rivales, hay compañeros de juego.</li>
              <li><strong>Constancia:</strong> El valor del esfuerzo diario, desde el primer saque hasta el último tanto.</li>
              <li><strong>Inclusión:</strong> Un deporte abierto a todos, sin importar edad, género o condición.</li>
            </ul>
          </div>
        </section>

        {/* 4. La Galotxeta: Nuestra Casa */}
        <section className={styles.section}>
          <div className={styles.textBlock}>
            <h2 className={styles.sectionTitle}>La Galotxeta: Nuestra Casa</h2>
            <p className={styles.paragraph}>
              Nuestra actividad se centra en la Galotxeta del Polideportivo Municipal de Monóvar. Esta cancha, con sus dimensiones particulares y sus característicos "cajones", es el escenario donde la técnica y la tradición se dan la mano.
            </p>
            <p className={styles.paragraph}>
              Aunque el origen de las galotxetes se remonta al siglo XIX, hoy seguimos utilizando la reglamentación clásica: pelotas de trapo, red destensada y una puntuación que nos recuerda a nuestras raíces. No somos solo un club; somos los guardianes de un legado que nació en las antiguas cuadras y calles de nuestro municipio.
            </p>
          </div>
        </section>

        {/* 5. El Corazón del Club: Nuestra Escuela */}
        <section className={`${styles.section} ${styles.reverseSection}`}>
          <div className={styles.imageBlock}>
            <img src={escuelaGrupo} alt="Escuela de Galotxas" className={styles.sectionImage} />
          </div>
          <div className={styles.textBlock}>
            <h2 className={styles.sectionTitle}>El Corazón del Club: Nuestra Escuela</h2>
            <p className={styles.paragraph}>
              En nuestro club, la edad es solo un número. Contamos con una escuela activa que acoge a alumnos desde los 9 años, donde aprenden los secretos de la pelota de la mano de quienes llevan décadas practicándola. Contamos con jugadores de más de 70 años que siguen dando lecciones de maestría cada semana, demostrando que las galotxetes son un deporte para toda la vida.
            </p>
          </div>
        </section>

        {/* 6. Compromiso Social e Igualdad */}
        <section className={styles.section}>
          <div className={styles.textBlock}>
            <h2 className={styles.sectionTitle}>Compromiso Social e Igualdad</h2>
            <p className={styles.paragraph}>
              Creemos en un deporte sin barreras. Por ello, el nuevo club ha impulsado la incorporación de la mujer, trabajando activamente por un entorno igualitario en la cancha.
            </p>
            <p className={styles.paragraph}>
              Además, estamos profundamente integrados en la comunidad educativa de Monóvar. Colaboramos regularmente con el Instituto y los colegios locales en jornadas de formación. Actualmente, desarrollamos con ilusión un plan de acercamiento para los usuarios del C.O. El Molinet, apostando por el deporte como herramienta de inclusión para personas con diversidad funcional e intelectual.
            </p>
          </div>
          <div className={styles.imageBlock}>
            <img src={igualdadJugadora} alt="Igualdad en el deporte" className={styles.sectionImage} />
          </div>
        </section>

        {/* 7. Convivencia y Cultura */}
        <section className={`${styles.section} ${styles.reverseSection}`}>
          <div className={styles.imageBlock}>
            <img src={convivenciaGatxamiga} alt="Jornada de convivencia" className={styles.sectionImage} />
          </div>
          <div className={styles.textBlock}>
            <h2 className={styles.sectionTitle}>Convivencia y Cultura</h2>
            <p className={styles.paragraph}>
              La Galotxeta es también un centro social. Celebramos habitualmente jornadas de convivencia que trascienden lo deportivo. En estas citas, el aroma de las gatxamigas (nuestra cocina más tradicional) se mezcla con la emoción de las partidas.
            </p>
            <p className={styles.paragraph}>
              También abrimos nuestras puertas a la hermandad con otras modalidades, organizando partidas de exhibición contra clubes de Llargues o Trinquet, enriqueciendo así el panorama de la Pelota Valenciana.
            </p>
          </div>
        </section>

        {/* 8. Junta Directiva */}
        <section className={styles.boardSection}>
          <h2 className={styles.sectionTitle}>Junta Directiva</h2>
          <div className={styles.boardGrid}>
            <div className={styles.boardMember}>
              <div className={styles.memberPhotoPlaceholder}>Presidencia</div>
              <h3>Nombre y Apellidos</h3>
              <p>Presidente/a</p>
            </div>
            <div className={styles.boardMember}>
              <div className={styles.memberPhotoPlaceholder}>Secretaría</div>
              <h3>Nombre y Apellidos</h3>
              <p>Secretario/a</p>
            </div>
            <div className={styles.boardMember}>
              <div className={styles.memberPhotoPlaceholder}>Tesorería</div>
              <h3>Nombre y Apellidos</h3>
              <p>Tesorero/a</p>
            </div>
            <div className={styles.boardMember}>
              <div className={styles.memberPhotoPlaceholder}>Vocalía</div>
              <h3>Nombre y Apellidos</h3>
              <p>Vocales</p>
            </div>
          </div>
        </section>
      </div>
    </div>
  );
};
