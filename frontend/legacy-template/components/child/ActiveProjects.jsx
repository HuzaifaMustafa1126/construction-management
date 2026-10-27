import { Icon } from "@iconify/react";
import { activeProjects } from "../../data/activeProjectsData";
import styles from "./ActiveProjects.module.css";

const ActiveProjects = () => {
  return (
    <div className="col-12">
      <section className={`card h-100 border radius-8 ${styles.card}`}>
        <div
          className={`d-flex align-items-center justify-content-between gap-3 ${styles.header}`}
        >
          <h6 className={`mb-0 fw-bold ${styles.title}`}>Active Projects</h6>
          <a href="#active-projects" className={styles.viewAll}>
            View All
          </a>
        </div>

        <div className="table-responsive" id="active-projects">
          <table className={`table bordered-table mb-0 ${styles.table}`}>
            <thead>
              <tr>
                <th scope="col">#</th>
                <th scope="col">Project Name</th>
                <th scope="col">Client</th>
                <th scope="col">Progress</th>
                <th scope="col">Contract Value</th>
                <th scope="col">Expenses</th>
                <th scope="col">Expected Profit</th>
                <th scope="col">Status</th>
                <th scope="col" className="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              {activeProjects.map((project) => {
                const isOnTrack = project.status === "On Track";

                return (
                  <tr key={project.id}>
                    <td>{project.id}</td>
                    <td>{project.name}</td>
                    <td>{project.client}</td>
                    <td>
                      <div className={styles.progressCell}>
                        <div
                          className={styles.progressTrack}
                          role="progressbar"
                          aria-label={`${project.name} progress`}
                          aria-valuenow={project.progress}
                          aria-valuemin="0"
                          aria-valuemax="100"
                        >
                          <div
                            className={styles.progressBar}
                            style={{
                              width: `${project.progress}%`,
                              backgroundColor: project.progressColor,
                            }}
                          />
                        </div>
                        <span className={styles.progressValue}>
                          {project.progress}%
                        </span>
                      </div>
                    </td>
                    <td>{project.contractValue}</td>
                    <td>{project.expenses}</td>
                    <td className={styles.profit}>{project.expectedProfit}</td>
                    <td>
                      <span
                        className={`${styles.status} ${
                          isOnTrack ? styles.onTrack : styles.delayed
                        }`}
                      >
                        {project.status}
                      </span>
                    </td>
                    <td className="text-center">
                      <button
                        type="button"
                        className={styles.actionButton}
                        aria-label={`Open actions for ${project.name}`}
                      >
                        <Icon icon="solar:menu-dots-bold" width="18" />
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
};

export default ActiveProjects;
