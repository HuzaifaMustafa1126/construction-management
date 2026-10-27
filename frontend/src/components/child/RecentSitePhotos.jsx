import LightGallery from "lightgallery/react";
import { Link } from "react-router-dom";

// This sample data can later be replaced with photos returned by the upload API.
const recentSitePhotos = [
  {
    id: 1,
    image: "assets/images/gallery/gallery-img1.png",
    caption: "Morning - 8:30 AM",
    alt: "Morning construction site progress",
  },
  {
    id: 2,
    image: "assets/images/gallery/gallery-img2.png",
    caption: "Afternoon - 1:15 PM",
    alt: "Afternoon construction site progress",
  },
  {
    id: 3,
    image: "assets/images/gallery/gallery-img3.png",
    caption: "Evening - 5:45 PM",
    alt: "Evening construction site progress",
  },
];

const RecentSitePhotos = () => {
  return (
    <div className="card h-100 radius-8 border">
      <div className="card-body p-16">
        <div className="d-flex align-items-center justify-content-between gap-2 mb-12">
          <h6 className="mb-0 fw-bold text-lg">Recent Site Photos</h6>
          <Link
            to="/gallery-hover"
            className="text-primary-600 text-sm fw-medium hover-text-primary"
          >
            View All
          </Link>
        </div>

        <LightGallery selector=".recent-site-photo" speed={400}>
          <div className="row g-12">
            {recentSitePhotos.map((photo) => (
              <div className="col-sm-4 col-12" key={photo.id}>
                <figure className="mb-0">
                  {/* Theme Gallery Hover Effect 1 */}
                  <div className="hover-scale-img radius-8 overflow-hidden">
                    <a
                      href={photo.image}
                      className="recent-site-photo popup-img w-100 d-flex radius-8 overflow-hidden"
                      data-sub-html={photo.caption}
                      style={{ height: "clamp(140px, 12vw, 200px)" }}
                    >
                      <img
                        src={photo.image}
                        alt={photo.alt}
                        className="hover-scale-img__img w-100 h-100 object-fit-cover"
                      />
                    </a>
                  </div>
                  <figcaption className="text-primary-light text-sm fw-medium mt-8">
                    {photo.caption}
                  </figcaption>
                </figure>
              </div>
            ))}
          </div>
        </LightGallery>
      </div>
    </div>
  );
};

export default RecentSitePhotos;
