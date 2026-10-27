import LightGallery from "lightgallery/react";

const hoverEffects = [
  { title: "Hover Effect 1", style: "", images: [1, 2, 3, 4] },
  { title: "Hover Effect 2", style: "style-two", images: [5, 6, 7, 8] },
  { title: "Hover Effect 3", style: "style-three", images: [9, 10, 11, 12] },
  { title: "Hover Effect 4", style: "style-four", images: [1, 2, 3, 4] },
  { title: "Hover Effect 5", style: "style-five", images: [5, 6, 7, 8] },
  { title: "Hover Effect 6", style: "style-six", images: [9, 10, 11, 12] },
  { title: "Hover Effect 7", style: "style-seven", images: [1, 2, 3, 4] },
  { title: "Hover Effect 8", style: "style-eight", images: [5, 6, 7, 8] },
  { title: "Hover Effect 9", style: "style-nine", images: [9, 10, 11, 12] },
];

const GalleryHoverLayer = () => {
  return (
    <>
      {hoverEffects.map((effect) => (
        <div
          className="mt-24 card h-100 p-0 radius-12 overflow-hidden gallery-scale"
          key={effect.title}
        >
          <div className="card-header">
            <h6 className="mb-0">{effect.title}</h6>
          </div>
          <div className="card-body p-24">
            <div className="row gy-4">
              {effect.images.map((imageNumber) => {
                const imagePath = `assets/images/gallery/gallery-img${imageNumber}.png`;

                return (
                  <div
                    className="col-xxl-3 col-md-4 col-sm-6"
                    key={`${effect.title}-${imageNumber}`}
                  >
                    <div
                      className={`hover-scale-img ${effect.style} border radius-16 overflow-hidden p-8`}
                    >
                      <LightGallery>
                        <a
                          href={imagePath}
                          className="popup-img w-100 h-100 d-flex radius-12 overflow-hidden"
                        >
                          <img
                            src={imagePath}
                            alt="WowDash React Vite"
                            className="hover-scale-img__img w-100 h-100 object-fit-cover"
                          />
                        </a>
                      </LightGallery>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      ))}
    </>
  );
};

export default GalleryHoverLayer;
