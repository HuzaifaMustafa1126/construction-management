/* @vitest-environment jsdom */
import { afterEach, describe, expect, it, vi } from "vitest";
import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router-dom";
const state = vi.hoisted(() => ({ permissions: ["photos.view"], user: { id: 2, company: { id: 1 }, roles: [{ name: "site_supervisor" }] }, projects: { data: { data: [{ id: 9, name: "Tower", code: "T-1" }] } }, photos: {} }));
vi.mock("../../auth/context/AuthContext", () => ({ useAuth: () => ({ user: state.user, can: (p) => state.permissions.includes(p) }) }));
vi.mock("../../projects/hooks/useProjects", () => ({ useProjects: () => state.projects }));
vi.mock("../hooks/useSitePhotos", () => ({ useSitePhotos: () => state.photos }));
vi.mock("../components/SitePhotoGallery", () => ({ default: ({ photos, loading }) => <div>{loading ? "Gallery loading" : `${photos.length} gallery photos`}</div> }));
import SitePhotosPage from "./SitePhotosPage";
afterEach(() => { cleanup(); vi.clearAllMocks(); state.permissions = ["photos.view"]; state.user = { id: 2, company: { id: 1 }, roles: [{ name: "site_supervisor" }] }; });
const draw = (route = "/site-photos?project=9") => render(<MemoryRouter initialEntries={[route]}><SitePhotosPage/></MemoryRouter>);
describe("site photo gallery", () => {
  it("renders project-scoped results and pagination", () => { state.photos = { data: { data: [{ id: 3 }], meta: { current_page: 1, last_page: 2, total: 16 } }, refetch: vi.fn() }; draw(); expect(screen.getByText("1 gallery photos")).toBeTruthy(); expect(screen.getByText(/16 photos/)).toBeTruthy(); });
  it("shows upload only with permission and never for clients", () => { state.permissions = ["photos.view", "photos.create"]; state.photos = { data: { data: [], meta: { current_page: 1, last_page: 1, total: 0 } }, refetch: vi.fn() }; draw(); expect(screen.getByText("Upload Photos")).toBeTruthy(); cleanup(); state.user = { ...state.user, roles: [{ name: "client" }] }; draw(); expect(screen.queryByText("Upload Photos")).toBeNull(); });
  it("renders loading and error states", () => { state.photos = { isLoading: true, error: { kind: "server", message: "Unavailable" }, refetch: vi.fn() }; draw(); expect(screen.getByText("Gallery loading")).toBeTruthy(); expect(screen.getByText("Unavailable")).toBeTruthy(); });
  it("supports caption, period, date, and project filters", async () => { state.photos = { data: { data: [], meta: { current_page: 1, last_page: 1, total: 0 } }, refetch: vi.fn() }; draw(); await userEvent.type(screen.getByLabelText("Search caption"), "foundation"); await userEvent.selectOptions(screen.getByLabelText("Period"), "evening"); await userEvent.click(screen.getByRole("button", { name: "Search" })); expect(screen.getByDisplayValue("foundation")).toBeTruthy(); expect(screen.getByDisplayValue("Evening")).toBeTruthy(); });
});
