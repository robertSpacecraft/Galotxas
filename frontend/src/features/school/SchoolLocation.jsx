import styles from './SchoolPage.module.css';

export const SchoolLocation = ({ location }) => {
  if (!location?.name && !location?.locality && !location?.address) {
    return null;
  }

  return (
    <address className={styles.location}>
      {location.name ? <strong>{location.name}</strong> : null}
      {location.address ? <span>{location.address}</span> : null}
      {location.locality ? <span>{location.locality}</span> : null}
    </address>
  );
};
